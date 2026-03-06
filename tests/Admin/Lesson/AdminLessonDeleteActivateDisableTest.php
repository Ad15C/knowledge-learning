<?php

namespace App\Tests\Admin\Lesson;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Lesson;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AdminLessonDeleteActivateDisableTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();

        $this->databaseTool->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);
    }

    private function loginAsAdmin(): void
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture not found.');
        $this->client->loginUser($admin);
    }

    private function loginAsUser(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture not found.');
        $this->client->loginUser($user);
    }

    private function getAnyLesson(): Lesson
    {
        $lesson = $this->em->getRepository(Lesson::class)->findOneBy([]);
        self::assertNotNull($lesson, 'No lesson found in fixtures.');
        self::assertNotNull($lesson->getId(), 'Fixture lesson must have an id.');

        return $lesson;
    }

    private function setLessonActive(Lesson $lesson, bool $active): Lesson
    {
        $lesson->setIsActive($active);
        $this->em->flush();
        $this->em->clear();

        /** @var Lesson|null $reloaded */
        $reloaded = $this->em->getRepository(Lesson::class)->find($lesson->getId());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    private function extractDisableCsrfFromDeletePage(int $lessonId): string
    {
        $crawler = $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/delete', $lessonId));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter(sprintf('form[action="/admin/lesson/%d/disable"]', $lessonId));
        self::assertGreaterThan(0, $form->count(), 'Disable form not found on delete page.');

        $tokenNode = $form->filter('input[name="_token"]');
        self::assertGreaterThan(0, $tokenNode->count(), 'CSRF token input not found on delete page.');

        $token = (string) $tokenNode->attr('value');
        self::assertNotSame('', $token, 'CSRF token value is empty on delete page.');

        return $token;
    }

    private function extractActivateCsrfFromIndexPage(int $lessonId): string
    {
        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson');
        self::assertResponseIsSuccessful();

        $formNode = $crawler->filter(sprintf('form[action="/admin/lesson/%d/activate"]', $lessonId));
        self::assertGreaterThan(0, $formNode->count(), 'Activate form not found on index page for this lesson.');

        $tokenNode = $formNode->filter('input[name="_token"]');
        self::assertGreaterThan(0, $tokenNode->count(), 'CSRF token input not found in activate form.');

        $token = (string) $tokenNode->attr('value');
        self::assertNotSame('', $token, 'CSRF token value is empty in activate form.');

        return $token;
    }

    private function assertAnonymousRedirectsToLogin(): void
    {
        self::assertTrue($this->client->getResponse()->isRedirection(), 'Anonymous should be redirected.');

        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location, 'Expected redirect to /login.');
    }

    public function testDeleteConfirmGetOkAsAdmin(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lesson->setTitle('Leçon test suppression');
        $lesson->setPrice('49.90');
        $this->em->flush();

        $lessonId = (int) $lesson->getId();

        $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/delete', $lessonId));
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Désactiver une leçon');
        self::assertSelectorTextContains('.lesson-detail-title', 'Leçon test suppression');
        self::assertSelectorExists('form[action="/admin/lesson/' . $lessonId . '/disable"]');
        self::assertSelectorExists('input[name="_token"]');
        self::assertSelectorTextContains('button.btn.btn-danger', 'Confirmer la désactivation');
        self::assertSelectorExists('a.btn.btn-secondary[href="/admin/lesson"]');
        self::assertSelectorTextContains('.admin-alert.admin-alert-danger', 'Cette leçon sera désactivée');

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Cursus', $content);
        self::assertStringContainsString('Prix', $content);
    }

    public function testDeleteConfirmGetRedirectsWhenAnonymous(): void
    {
        $lesson = $this->getAnyLesson();
        $lessonId = (int) $lesson->getId();

        $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/delete', $lessonId));
        $this->assertAnonymousRedirectsToLogin();
    }

    public function testDeleteConfirmGetForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $lesson = $this->getAnyLesson();
        $lessonId = (int) $lesson->getId();

        $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/delete', $lessonId));
        self::assertResponseStatusCodeSame(403);
    }

    public function testDisableSetsIsActiveFalseAndRedirectsWithValidCsrf(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lesson = $this->setLessonActive($lesson, true);
        $lessonId = (int) $lesson->getId();

        $csrf = $this->extractDisableCsrfFromDeletePage($lessonId);

        $this->client->request('POST', sprintf('https://localhost/admin/lesson/%d/disable', $lessonId), [
            '_token' => $csrf,
        ]);

        self::assertResponseRedirects('/admin/lesson');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Leçon archivée.');

        $this->em->clear();

        /** @var Lesson|null $reloaded */
        $reloaded = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive(), 'Lesson should be inactive after disable.');
    }

    public function testDisableWithInvalidCsrfReturns403AndDoesNotChange(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lesson = $this->setLessonActive($lesson, true);
        $lessonId = (int) $lesson->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/lesson/%d/disable', $lessonId), [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive(), 'Lesson should remain active if CSRF is invalid.');
    }

    public function testDisableRedirectsWhenAnonymous(): void
    {
        $lesson = $this->getAnyLesson();
        $lessonId = (int) $lesson->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/lesson/%d/disable', $lessonId), [
            '_token' => 'whatever',
        ]);

        $this->assertAnonymousRedirectsToLogin();
    }

    public function testDisableForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $lesson = $this->getAnyLesson();
        $lessonId = (int) $lesson->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/lesson/%d/disable', $lessonId), [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivateSetsIsActiveTrueAndRedirectsWithValidCsrf(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lesson = $this->setLessonActive($lesson, false);
        $lessonId = (int) $lesson->getId();

        $csrf = $this->extractActivateCsrfFromIndexPage($lessonId);

        $this->client->request('POST', sprintf('https://localhost/admin/lesson/%d/activate', $lessonId), [
            '_token' => $csrf,
        ]);

        self::assertResponseRedirects('/admin/lesson');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Leçon restaurée.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive(), 'Lesson should be active after activate.');
    }

    public function testActivateWithInvalidCsrfReturns403AndDoesNotChange(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lesson = $this->setLessonActive($lesson, false);
        $lessonId = (int) $lesson->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/lesson/%d/activate', $lessonId), [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive(), 'Lesson should remain inactive if CSRF is invalid.');
    }

    public function testActivateRedirectsWhenAnonymous(): void
    {
        $lesson = $this->getAnyLesson();
        $lessonId = (int) $lesson->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/lesson/%d/activate', $lessonId), [
            '_token' => 'whatever',
        ]);

        $this->assertAnonymousRedirectsToLogin();
    }

    public function testActivateForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $lesson = $this->getAnyLesson();
        $lessonId = (int) $lesson->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/lesson/%d/activate', $lessonId), [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}