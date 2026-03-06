<?php

namespace App\Tests\Workflow\Admin;

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

class AdminWorkflowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient([], [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SERVER_PORT' => 443,
        ]);

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var DatabaseToolCollection $dbTools */
        $dbTools = static::getContainer()->get(DatabaseToolCollection::class);
        $dbTools->get()->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);
    }

    private function getAdmin(): User
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture introuvable.');

        return $admin;
    }

    private function loginAsAdmin(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
    }

    private function extractCsrfTokenFromCrawler(Crawler $crawler, string $formSelector): string
    {
        $tokenNode = $crawler->filter($formSelector . ' input[name="_token"]')->first();

        self::assertGreaterThan(
            0,
            $tokenNode->count(),
            sprintf('Aucun token CSRF trouvé pour le sélecteur "%s".', $formSelector)
        );

        $token = (string) $tokenNode->attr('value');
        self::assertNotEmpty($token, 'Token CSRF vide.');

        return $token;
    }

    private function getThemeByName(string $name): Theme
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => $name]);

        self::assertNotNull($theme, sprintf('Thème "%s" introuvable.', $name));

        return $theme;
    }

    private function getCursusByName(string $name): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy(['name' => $name]);

        self::assertNotNull($cursus, sprintf('Cursus "%s" introuvable.', $name));

        return $cursus;
    }

    private function getLessonByTitle(string $title): Lesson
    {
        $lesson = $this->em->getRepository(Lesson::class)->findOneBy(['title' => $title]);

        self::assertNotNull($lesson, sprintf('Leçon "%s" introuvable.', $title));

        return $lesson;
    }

    public function testAdminCompleteWorkflowThemeCursusLesson(): void
    {
        $this->loginAsAdmin();

        // =========================
        // 1) Navigation dashboard
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bienvenue');
        self::assertSelectorExists('a[href="/admin/themes"]');
        self::assertSelectorExists('a[href="/admin/cursus"]');
        self::assertSelectorExists('a[href="/admin/lesson"]');

        // =========================
        // 2) Navigation liste thèmes
        // =========================
        $crawler = $this->client->clickLink('Liste des thèmes');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Thèmes');
        self::assertSelectorExists('a[href="/admin/themes/new"]');

        // =========================
        // 3) Création thème
        // =========================
        $crawler = $this->client->clickLink('+ Créer un thème');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer un thème');

        $form = $crawler->filter('form')->first()->form();
        $form['theme[name]'] = 'Thème Workflow';
        $form['theme[description]'] = 'Description thème workflow';
        $form['theme[image]'] = 'images/themes/workflow/theme.jpg';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/themes');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Thème créé.');
        self::assertStringContainsString('Thème Workflow', $this->client->getResponse()->getContent() ?? '');

        $this->em->clear();
        $theme = $this->getThemeByName('Thème Workflow');
        self::assertSame('Description thème workflow', $theme->getDescription());
        self::assertTrue($theme->isActive());

        // =========================
        // 4) Navigation liste cursus
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cursus');
        self::assertSelectorExists('a[href="/admin/cursus/new"]');

        // =========================
        // 5) Création cursus
        // =========================
        $crawler = $this->client->clickLink('+ Créer un cursus');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer un cursus');

        $form = $crawler->filter('form')->first()->form();
        $form['cursus[name]'] = 'Cursus Workflow';
        $form['cursus[theme]'] = (string) $theme->getId();
        $form['cursus[price]'] = '123.45';
        $form['cursus[description]'] = 'Description cursus workflow';
        $form['cursus[image]'] = 'images/cursus/workflow/cursus.jpg';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/cursus');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Cursus créé.');
        self::assertStringContainsString('Cursus Workflow', $this->client->getResponse()->getContent() ?? '');

        $this->em->clear();
        $cursus = $this->getCursusByName('Cursus Workflow');
        self::assertSame('Description cursus workflow', $cursus->getDescription());
        self::assertEquals(123.45, (float) $cursus->getPrice());
        self::assertTrue($cursus->isActive());
        self::assertSame('Thème Workflow', $cursus->getTheme()?->getName());

        // =========================
        // 6) Navigation liste leçons
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Leçons');
        self::assertSelectorExists('a[href="/admin/lesson/new"]');

        // =========================
        // 7) Création leçon
        // =========================
        $crawler = $this->client->clickLink('+ Créer une leçon');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer une leçon');

        $form = $crawler->filter('form')->first()->form();
        $form['lesson[title]'] = 'Leçon Workflow';
        $form['lesson[cursus]'] = (string) $cursus->getId();
        $form['lesson[price]'] = '49.90';
        $form['lesson[fiche]'] = '<p>Fiche workflow</p>';
        $form['lesson[videoUrl]'] = 'https://youtu.be/workflow';
        $form['lesson[image]'] = 'images/lessons/workflow/lesson.jpg';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/lesson');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Leçon créée.');
        self::assertStringContainsString('Leçon Workflow', $this->client->getResponse()->getContent() ?? '');

        $this->em->clear();
        $lesson = $this->getLessonByTitle('Leçon Workflow');
        self::assertEquals(49.90, (float) $lesson->getPrice());
        self::assertTrue($lesson->isActive());
        self::assertSame('Cursus Workflow', $lesson->getCursus()?->getName());

        // =========================
        // 8) Modification thème
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/' . $theme->getId() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Modifier');

        $form = $crawler->filter('form')->first()->form();
        $form['theme[name]'] = 'Thème Workflow Modifié';
        $form['theme[description]'] = 'Description thème workflow modifiée';
        $form['theme[image]'] = 'images/themes/workflow/theme-modified.jpg';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/themes');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Thème modifié.');
        self::assertStringContainsString('Thème Workflow Modifié', $this->client->getResponse()->getContent() ?? '');

        $this->em->clear();
        $theme = $this->getThemeByName('Thème Workflow Modifié');
        self::assertSame('Description thème workflow modifiée', $theme->getDescription());

        // =========================
        // 9) Modification cursus
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Modifier');

        $form = $crawler->filter('form')->first()->form();
        $form['cursus[name]'] = 'Cursus Workflow Modifié';
        $form['cursus[theme]'] = (string) $theme->getId();
        $form['cursus[price]'] = '150.00';
        $form['cursus[description]'] = 'Description cursus workflow modifiée';
        $form['cursus[image]'] = 'images/cursus/workflow/cursus-modified.jpg';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/cursus');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Cursus modifié.');
        self::assertStringContainsString('Cursus Workflow Modifié', $this->client->getResponse()->getContent() ?? '');

        $this->em->clear();
        $cursus = $this->getCursusByName('Cursus Workflow Modifié');
        self::assertEquals(150.00, (float) $cursus->getPrice());
        self::assertSame('Description cursus workflow modifiée', $cursus->getDescription());
        self::assertSame('Thème Workflow Modifié', $cursus->getTheme()?->getName());

        // =========================
        // 10) Modification leçon
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/' . $lesson->getId() . '/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Modifier');

        $form = $crawler->filter('form')->first()->form();
        $form['lesson[title]'] = 'Leçon Workflow Modifiée';
        $form['lesson[cursus]'] = (string) $cursus->getId();
        $form['lesson[price]'] = '59.90';
        $form['lesson[fiche]'] = '<p>Fiche workflow modifiée</p>';
        $form['lesson[videoUrl]'] = 'https://youtu.be/workflow-modified';
        $form['lesson[image]'] = 'images/lessons/workflow/lesson-modified.jpg';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/lesson');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Leçon modifiée.');
        self::assertStringContainsString('Leçon Workflow Modifiée', $this->client->getResponse()->getContent() ?? '');

        $this->em->clear();
        $lesson = $this->getLessonByTitle('Leçon Workflow Modifiée');
        self::assertEquals(59.90, (float) $lesson->getPrice());
        self::assertSame('Cursus Workflow Modifié', $lesson->getCursus()?->getName());

        // =========================
        // 11) Archivage thème
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/' . $theme->getId() . '/delete');
        self::assertResponseIsSuccessful();

        $token = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/themes/%d/disable"]', $theme->getId())
        );

        $this->client->request('POST', 'https://localhost/admin/themes/' . $theme->getId() . '/disable', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/themes');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Thème désactivé.');

        $this->em->clear();
        $theme = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertNotNull($theme);
        self::assertFalse($theme->isActive());

        $this->client->request('GET', 'https://localhost/admin/themes?status=archived');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Thème Workflow Modifié', $this->client->getResponse()->getContent() ?? '');

        // =========================
        // 12) Archivage cursus
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/delete');
        self::assertResponseIsSuccessful();

        $token = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/cursus/%d/disable"]', $cursus->getId())
        );

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/cursus');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Cursus archivé.');

        $this->em->clear();
        $cursus = $this->em->getRepository(Cursus::class)->find($cursus->getId());
        self::assertNotNull($cursus);
        self::assertFalse($cursus->isActive());

        $this->client->request('GET', 'https://localhost/admin/cursus?status=archived');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Cursus Workflow Modifié', $this->client->getResponse()->getContent() ?? '');

        // =========================
        // 13) Archivage leçon
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/' . $lesson->getId() . '/delete');
        self::assertResponseIsSuccessful();

        $token = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/lesson/%d/disable"]', $lesson->getId())
        );

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/disable', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/lesson');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Leçon archivée.');

        $this->em->clear();
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());
        self::assertNotNull($lesson);
        self::assertFalse($lesson->isActive());

        $this->client->request('GET', 'https://localhost/admin/lesson?status=archived');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Leçon Workflow Modifiée', $this->client->getResponse()->getContent() ?? '');

        // =========================
        // 14) Réactivation thème
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes?status=archived');
        self::assertResponseIsSuccessful();

        $themeActivateToken = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/themes/%d/activate"]', $theme->getId())
        );

        $this->client->request('POST', 'https://localhost/admin/themes/' . $theme->getId() . '/activate', [
            '_token' => $themeActivateToken,
        ]);

        self::assertResponseRedirects('/admin/themes');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Thème réactivé.');

        $this->em->clear();
        $theme = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertNotNull($theme);
        self::assertTrue($theme->isActive());

        // =========================
        // 15) Réactivation cursus
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus?status=archived');
        self::assertResponseIsSuccessful();

        $cursusActivateToken = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/cursus/%d/activate"]', $cursus->getId())
        );

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => $cursusActivateToken,
        ]);

        self::assertResponseRedirects('/admin/cursus');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Cursus réactivé.');

        $this->em->clear();
        $cursus = $this->em->getRepository(Cursus::class)->find($cursus->getId());
        self::assertNotNull($cursus);
        self::assertTrue($cursus->isActive());

        // =========================
        // 16) Réactivation leçon
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson?status=archived');
        self::assertResponseIsSuccessful();

        $lessonActivateToken = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/lesson/%d/activate"]', $lesson->getId())
        );

        $this->client->request('POST', 'https://localhost/admin/lesson/' . $lesson->getId() . '/activate', [
            '_token' => $lessonActivateToken,
        ]);

        self::assertResponseRedirects('/admin/lesson');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Leçon restaurée.');

        $this->em->clear();
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());
        self::assertNotNull($lesson);
        self::assertTrue($lesson->isActive());

        // =========================
        // 17) Vérification finale listes actives
        // =========================
        $this->client->request('GET', 'https://localhost/admin/themes?status=active');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Thème Workflow Modifié', $this->client->getResponse()->getContent() ?? '');

        $this->client->request('GET', 'https://localhost/admin/cursus?status=active');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Cursus Workflow Modifié', $this->client->getResponse()->getContent() ?? '');

        $this->client->request('GET', 'https://localhost/admin/lesson?status=active');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Leçon Workflow Modifiée', $this->client->getResponse()->getContent() ?? '');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}