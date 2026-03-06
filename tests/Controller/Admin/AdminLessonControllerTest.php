<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AdminLessonControllerTest extends WebTestCase
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

    // -------------------------
    // HELPERS
    // -------------------------

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
        self::assertNotNull($lesson, 'No lesson found (fixtures missing?).');

        return $lesson;
    }

    private function getAnyActiveCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy(['isActive' => true]);
        self::assertNotNull($cursus, 'No active cursus found (fixtures missing?).');

        return $cursus;
    }

    private function getThemeByName(string $name): Theme
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => $name]);
        self::assertNotNull($theme, sprintf('Theme "%s" not found.', $name));

        return $theme;
    }

    private function requestIndex(array $query = []): Crawler
    {
        $qs = $query ? ('?' . http_build_query($query)) : '';

        return $this->client->request('GET', 'https://localhost/admin/lesson' . $qs);
    }

    private function extractHiddenToken(Crawler $crawler, string $formSelector, string $inputName = '_token'): string
    {
        $input = $crawler->filter($formSelector . ' input[name="' . $inputName . '"]');

        self::assertGreaterThan(
            0,
            $input->count(),
            sprintf('Hidden input "%s" not found in form "%s".', $inputName, $formSelector)
        );

        $token = (string) $input->first()->attr('value');
        self::assertNotSame('', $token, 'CSRF token value is empty.');

        return $token;
    }

    private function getDisplayedLessonTitles(Crawler $crawler): array
    {
        $titles = [];

        $crawler->filter('.lesson-card .lesson-title > span:first-child')->each(function (Crawler $node) use (&$titles) {
            $titles[] = trim($node->text());
        });

        return $titles;
    }

    // -------------------------
    // SECURITY (NOT LOGGED)
    // -------------------------

    public function testIndexRedirectsToLoginWhenNotLoggedIn(): void
    {
        $this->client->request('GET', 'https://localhost/admin/lesson');
        self::assertResponseRedirects('/login');
    }

    public function testNewGetRedirectsToLoginWhenNotLoggedIn(): void
    {
        $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseRedirects('/login');
    }

    public function testNewPostRedirectsToLoginWhenNotLoggedIn(): void
    {
        $this->client->request('POST', 'https://localhost/admin/lesson/new', [
            'lesson' => ['title' => 'Hack'],
        ]);
        self::assertResponseRedirects('/login');
    }

    public function testEditGetRedirectsToLoginWhenNotLoggedIn(): void
    {
        $lesson = $this->getAnyLesson();
        $this->client->request('GET', 'https://localhost/admin/lesson/' . $lesson->getId() . '/edit');
        self::assertResponseRedirects('/login');
    }

    public function testEditPostRedirectsToLoginWhenNotLoggedIn(): void
    {
        $lesson = $this->getAnyLesson();
        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/edit', [
            'lesson' => ['title' => 'Hack'],
        ]);
        self::assertResponseRedirects('/login');
    }

    public function testDeleteConfirmRedirectsToLoginWhenNotLoggedIn(): void
    {
        $lesson = $this->getAnyLesson();
        $this->client->request('GET', 'https://localhost/admin/lesson/' . $lesson->getId() . '/delete');
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

    // -------------------------
    // SECURITY (ROLE_USER)
    // -------------------------

    public function testIndexIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $this->client->request('GET', 'https://localhost/admin/lesson');
        self::assertResponseStatusCodeSame(403);
    }

    public function testNewGetIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testNewPostIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $this->client->request('POST', 'https://localhost/admin/lesson/new', [
            'lesson' => ['title' => 'Hack'],
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditGetIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $lesson = $this->getAnyLesson();

        $this->client->request('GET', 'https://localhost/admin/lesson/' . $lesson->getId() . '/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditPostIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $lesson = $this->getAnyLesson();

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/edit', [
            'lesson' => ['title' => 'Hack'],
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteConfirmIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $lesson = $this->getAnyLesson();

        $this->client->request('GET', 'https://localhost/admin/lesson/' . $lesson->getId() . '/delete');
        self::assertResponseStatusCodeSame(403);
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

    // -------------------------
    // FUNCTIONAL: INDEX + FILTERS
    // -------------------------

    public function testIndexPageIsSuccessfulAndShowsCards(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1', 'Leçons');
        self::assertGreaterThan(0, $crawler->filter('.lesson-card')->count(), 'No lesson cards found.');
    }

    public function testIndexFilterByQueryReturnsMatchingTitles(): void
    {
        $this->loginAsAdmin();

        $any = $this->getAnyLesson();
        $needle = mb_substr((string) $any->getTitle(), 0, 6);

        $crawler = $this->requestIndex(['q' => $needle]);
        self::assertResponseIsSuccessful();

        $titles = $this->getDisplayedLessonTitles($crawler);
        self::assertNotEmpty($titles);

        foreach ($titles as $title) {
            self::assertStringContainsStringIgnoringCase($needle, $title);
        }
    }

    public function testIndexFilterByStatusArchivedShowsOnlyArchivedBadges(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lesson->setIsActive(false);
        $this->em->flush();

        $crawler = $this->requestIndex(['status' => 'archived']);
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('badge-archived', $html);
        self::assertStringNotContainsString('badge-active', $html);
        self::assertGreaterThan(0, $crawler->filter('.lesson-card')->count());
    }

    public function testIndexSortTitleAscWorks(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['sort' => 'title_asc']);
        self::assertResponseIsSuccessful();

        $displayed = $this->getDisplayedLessonTitles($crawler);
        $sorted = $displayed;
        $lower = array_map('mb_strtolower', $sorted);
        array_multisort($lower, SORT_ASC, $sorted);

        self::assertSame($sorted, $displayed);
    }

    public function testIndexFilterByThemeAndCursus(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique'])
            ?? $this->em->getRepository(Theme::class)->findOneBy([]);

        self::assertNotNull($theme);

        $cursus = $this->em->getRepository(Cursus::class)->findOneBy(['theme' => $theme])
            ?? $this->getAnyActiveCursus();

        self::assertNotNull($cursus);

        $crawler = $this->requestIndex([
            'theme' => $theme->getId(),
            'cursus' => $cursus->getId(),
        ]);

        self::assertResponseIsSuccessful();

        $crawler->filter('.lesson-card .lesson-meta')->each(function (Crawler $node) use ($cursus) {
            self::assertStringContainsString((string) $cursus->getName(), $node->text());
        });
    }

    // -------------------------
    // FUNCTIONAL: NEW
    // -------------------------

    public function testNewGetShowsForm(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="lesson[title]"]');
        self::assertSelectorExists('select[name="lesson[cursus]"]');
    }

    public function testNewPostValidCreatesLessonAndRedirects(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyActiveCursus();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

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

        $this->em->clear();
        $created = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson Test New']);
        self::assertNotNull($created);
        self::assertTrue($created->isActive());
        self::assertEquals(12.50, (float) $created->getPrice());
    }

    public function testNewPostInvalidStaysOnPageAndDoesNotCreate(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyActiveCursus();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer')->form([
            'lesson[title]' => '',
            'lesson[cursus]' => (string) $cursus->getId(),
            'lesson[price]' => '10.00',
            'lesson[fiche]' => '',
            'lesson[videoUrl]' => '',
            'lesson[image]' => '',
        ]);

        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $content = (string) $this->client->getResponse()->getContent();

        $hasCustom = str_contains($content, 'Le titre est obligatoire.');
        $hasDefault = str_contains($content, 'This value should not be blank')
            || str_contains($content, 'Cette valeur ne doit pas être vide');

        self::assertTrue($hasCustom || $hasDefault, 'Expected a validation message in the response content.');

        $this->em->clear();
        $created = $this->em->getRepository(Lesson::class)->findOneBy(['title' => '']);
        self::assertNull($created);
    }

    // -------------------------
    // FUNCTIONAL: EDIT
    // -------------------------

    public function testEditGetShowsFormAndPrefilledValues(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lesson->setTitle('Titre avant edit');
        $lesson->setFiche('Fiche avant edit');
        $lesson->setPrice(9.99);
        $lesson->setImage('/img-avant.jpg');
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/' . $lesson->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();

        self::assertSame('Titre avant edit', $form['lesson[title]']->getValue());
        self::assertSame('Fiche avant edit', $form['lesson[fiche]']->getValue());
        self::assertStringContainsString('9', (string) $form['lesson[price]']->getValue());
        self::assertSame('/img-avant.jpg', $form['lesson[image]']->getValue());
    }

    public function testEditPostValidUpdatesLessonAndRedirects(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/' . $id . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'lesson[title]' => 'Lesson (modifiée)',
            'lesson[price]' => '33.30',
            'lesson[fiche]' => 'Nouvelle fiche',
            'lesson[videoUrl]' => 'https://example.com/video',
            'lesson[image]' => '/img-new.jpg',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/lesson');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($id);
        self::assertNotNull($reloaded);

        self::assertSame('Lesson (modifiée)', $reloaded->getTitle());
        self::assertSame('Nouvelle fiche', $reloaded->getFiche());
        self::assertSame('/img-new.jpg', $reloaded->getImage());
        self::assertEquals(33.30, (float) $reloaded->getPrice());
    }

    // -------------------------
    // FUNCTIONAL: DELETE CONFIRM + DISABLE + ACTIVATE + CSRF
    // -------------------------

    public function testDeleteConfirmPageIsSuccessfulAndHasDisableToken(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/' . $id . '/delete');
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('form[action*="/admin/lesson/' . $id . '/disable"]');
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

    public function testActivateWithValidCsrfSetsIsActiveTrue(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();

        $lesson->setIsActive(false);
        $this->em->flush();

        $crawler = $this->requestIndex(['status' => 'archived']);
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('form[action*="/admin/lesson/' . $id . '/activate"]');

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