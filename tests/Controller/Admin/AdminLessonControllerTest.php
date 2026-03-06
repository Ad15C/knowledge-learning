<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AdminLessonControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private CsrfTokenManagerInterface $csrf;
    private $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->csrf = static::getContainer()->get(CsrfTokenManagerInterface::class);

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

        self::assertNotNull($admin);
        $this->client->loginUser($admin, 'main');
    }

    private function loginAsUser(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user);
        $this->client->loginUser($user, 'main');
    }

    private function getAnyLesson(): Lesson
    {
        $lesson = $this->em->getRepository(Lesson::class)->findOneBy([]);
        self::assertNotNull($lesson);

        return $lesson;
    }

    private function getAnyActiveCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy(['isActive' => true]);
        self::assertNotNull($cursus);

        return $cursus;
    }

    private function requestIndex(array $query = []): Crawler
    {
        $qs = $query ? ('?' . http_build_query($query)) : '';

        return $this->client->request('GET', 'https://localhost/admin/lesson' . $qs);
    }

    private function extractHiddenToken(Crawler $crawler, string $formSelector, string $inputName = '_token'): string
    {
        $input = $crawler->filter($formSelector . ' input[name="' . $inputName . '"]');

        self::assertGreaterThan(0, $input->count());

        $token = (string) $input->first()->attr('value');
        self::assertNotSame('', $token);

        return $token;
    }

    private function tokenFromManagerUsingClientSession(string $tokenId, string $warmupUrl): string
    {
        $this->client->request('GET', $warmupUrl);

        $container = static::getContainer();

        $sessionFactory = $container->get('session.factory');
        $tmpSession = $sessionFactory->createSession();
        $sessionName = $tmpSession->getName();

        $cookie = $this->client->getCookieJar()->get($sessionName);
        self::assertNotNull($cookie);

        $sessionId = $cookie->getValue();

        $session = $sessionFactory->createSession();
        if (method_exists($session, 'setId')) {
            $session->setId($sessionId);
        }
        if (!$session->isStarted()) {
            $session->start();
        }

        $requestStack = $container->get('request_stack');

        $req = Request::create('https://localhost/');
        $req->setSession($session);
        $requestStack->push($req);

        try {
            $token = $this->csrf->getToken($tokenId)->getValue();
            $session->save();

            return $token;
        } finally {
            $requestStack->pop();
        }
    }

    public function testIndexRedirectsToLoginWhenNotLoggedIn(): void
    {
        $this->client->request('GET', 'https://localhost/admin/lesson');
        self::assertResponseRedirects('/login');
    }

    public function testDisablePostRedirectsToLoginWhenNotLoggedIn(): void
    {
        $lesson = $this->getAnyLesson();

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/disable', [
            '_token' => 'whatever',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testActivatePostRedirectsToLoginWhenNotLoggedIn(): void
    {
        $lesson = $this->getAnyLesson();

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/activate', [
            '_token' => 'whatever',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testDisablePostIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $lesson = $this->getAnyLesson();

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/disable', [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivatePostIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $lesson = $this->getAnyLesson();

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/activate', [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisablePostIsForbiddenForRoleUserEvenWithValidCsrf(): void
    {
        $this->loginAsUser();
        $lesson = $this->getAnyLesson();

        $token = $this->tokenFromManagerUsingClientSession(
            'lesson_disable' . $lesson->getId(),
            'https://localhost/dashboard'
        );

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/disable', [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivatePostIsForbiddenForRoleUserEvenWithValidCsrf(): void
    {
        $this->loginAsUser();
        $lesson = $this->getAnyLesson();

        $token = $this->tokenFromManagerUsingClientSession(
            'lesson_activate' . $lesson->getId(),
            'https://localhost/dashboard'
        );

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/activate', [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexPageIsSuccessfulAndShowsCards(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Leçons');
        self::assertSelectorExists('form.admin-filters');
        self::assertGreaterThan(0, $crawler->filter('.lesson-card')->count());
    }

    public function testNewPostValidCreatesLessonAndRedirects(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyActiveCursus();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Créer une leçon');

        $form = $crawler->selectButton('Créer')->form([
            'lesson[title]' => 'Lesson Test New',
            'lesson[cursus]' => (string) $cursus->getId(),
            'lesson[price]' => '12.50',
            'lesson[fiche]' => 'Fiche test',
            'lesson[videoUrl]' => '',
            'lesson[image]' => '',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/lesson');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Leçon créée.');

        $this->em->clear();
        $created = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson Test New']);
        self::assertNotNull($created);
        self::assertTrue($created->isActive());
    }

    public function testDeleteConfirmPageIsSuccessfulAndHasDisableToken(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/' . $id . '/delete');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Désactiver une leçon');
        self::assertSelectorExists('form[action*="/admin/lesson/' . $id . '/disable"]');
        self::assertSelectorTextContains('button.btn.btn-danger', 'Confirmer la désactivation');

        $this->extractHiddenToken($crawler, 'form[action*="/admin/lesson/' . $id . '/disable"]');
    }

    public function testDisableWithValidCsrfSetsIsActiveFalse(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $lesson->setIsActive(true);
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/' . $id . '/delete');
        self::assertResponseIsSuccessful();

        $token = $this->extractHiddenToken($crawler, 'form[action*="/admin/lesson/' . $id . '/disable"]');

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $id . '/disable', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/lesson');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Leçon archivée.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
    }

    public function testDisableWithMissingCsrfReturns403AndDoesNotChangeState(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $lesson->setIsActive(true);
        $this->em->flush();

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $id . '/disable');

        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive());
    }

    public function testDisableWithInvalidCsrfReturns403AndDoesNotChangeState(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $lesson->setIsActive(true);
        $this->em->flush();

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $id . '/disable', [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive());
    }

    public function testActivateWithValidCsrfSetsIsActiveTrue(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $lesson->setIsActive(false);
        $this->em->flush();

        $crawler = $this->requestIndex(['status' => 'archived']);
        self::assertResponseIsSuccessful();

        $token = $this->extractHiddenToken($crawler, 'form[action*="/admin/lesson/' . $id . '/activate"]');

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $id . '/activate', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/lesson');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Leçon restaurée.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive());
    }

    public function testActivateWithMissingCsrfReturns403AndDoesNotChangeState(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $lesson->setIsActive(false);
        $this->em->flush();

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $id . '/activate');

        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
    }

    public function testActivateWithInvalidCsrfReturns403AndDoesNotChangeState(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $lesson->setIsActive(false);
        $this->em->flush();

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $id . '/activate', [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}