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

class AdminBusinessWorkflowTest extends WebTestCase
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

    private function getThemeByName(string $name): Theme
    {
        $theme = $this->em->getRepository(Theme::class)
            ->findOneBy(['name' => $name]);

        self::assertNotNull($theme, sprintf('Thème "%s" introuvable.', $name));

        return $theme;
    }

    private function getCursusByName(string $name): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)
            ->findOneBy(['name' => $name]);

        self::assertNotNull($cursus, sprintf('Cursus "%s" introuvable.', $name));

        return $cursus;
    }

    private function getLessonByTitle(string $title): Lesson
    {
        $lesson = $this->em->getRepository(Lesson::class)
            ->findOneBy(['title' => $title]);

        self::assertNotNull($lesson, sprintf('Leçon "%s" introuvable.', $title));

        return $lesson;
    }

    private function getSelectOptionTexts(Crawler $crawler, string $selector): array
    {
        return array_map(
            static fn(string $text): string => trim($text),
            $crawler->filter($selector)->each(
                static fn(Crawler $node): string => (string) $node->text()
            )
        );
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

    public function testCursusCreationIsBlockedWhenNoActiveThemeExists(): void
    {
        $this->loginAsAdmin();

        $themes = $this->em->getRepository(Theme::class)->findAll();
        self::assertNotEmpty($themes);

        foreach ($themes as $theme) {
            $theme->setIsActive(false);
        }
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();

        self::assertStringContainsString(
            'Aucun thème actif disponible. Crée ou réactive un thème avant de créer un cursus.',
            $this->client->getResponse()->getContent() ?? ''
        );

        self::assertSelectorExists('button[type="submit"][disabled]');

        $form = $crawler->filter('form')->first()->form();
        $form['cursus[name]'] = 'Cursus Interdit';
        $form['cursus[price]'] = '10.00';
        $form['cursus[description]'] = 'Ne devrait pas être créé';
        $form['cursus[image]'] = '';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/themes');
        $this->client->followRedirect();

        self::assertSelectorTextContains(
            '.flash-error',
            'Aucun thème actif disponible. Crée ou réactive un thème avant de créer un cursus.'
        );

        $this->em->clear();
        $created = $this->em->getRepository(Cursus::class)->findOneBy(['name' => 'Cursus Interdit']);
        self::assertNull($created);
    }

    public function testInactiveThemesAreNotAvailableInNewCursusForm(): void
    {
        $this->loginAsAdmin();

        $musique = $this->getThemeByName('Musique');
        $informatique = $this->getThemeByName('Informatique');

        $musique->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();

        $options = $this->getSelectOptionTexts($crawler, 'select[name="cursus[theme]"] option');

        self::assertContains('Informatique', $options);
        self::assertNotContains('Musique', $options);
    }

    public function testCurrentInactiveThemeRemainsAvailableInCursusEditForm(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getCursusByName('Cursus d’initiation à la guitare');
        $theme = $cursus->getTheme();

        self::assertNotNull($theme);

        $theme->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $cursus = $this->getCursusByName('Cursus d’initiation à la guitare');

        $crawler = $this->client->request(
            'GET',
            'https://localhost/admin/cursus/' . $cursus->getId() . '/edit'
        );
        self::assertResponseIsSuccessful();

        $options = $this->getSelectOptionTexts($crawler, 'select[name="cursus[theme]"] option');

        self::assertContains('Musique', $options, 'Le thème courant inactif doit rester sélectionnable en édition.');
    }

    public function testInactiveCursusAreNotAvailableInNewLessonForm(): void
    {
        $this->loginAsAdmin();

        $allCursus = $this->em->getRepository(Cursus::class)->findAll();
        self::assertNotEmpty($allCursus);

        foreach ($allCursus as $cursus) {
            $cursus->setIsActive(false);
        }
        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        $options = $this->getSelectOptionTexts($crawler, 'select[name="lesson[cursus]"] option');

        self::assertContains('— Choisir un cursus —', $options);
        self::assertNotContains('Cursus d’initiation à la guitare', $options);
        self::assertNotContains('Cursus d’initiation au piano', $options);
        self::assertCount(1, $options, 'Seul le placeholder devrait rester quand aucun cursus actif n’existe.');

        $created = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Leçon Impossible']);
        self::assertNull($created);
    }

    public function testCurrentInactiveCursusRemainsAvailableInLessonEditForm(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getLessonByTitle('Découverte de l’instrument');
        $cursus = $lesson->getCursus();

        self::assertNotNull($cursus);

        $cursus->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $lesson = $this->getLessonByTitle('Découverte de l’instrument');

        $crawler = $this->client->request(
            'GET',
            'https://localhost/admin/lesson/' . $lesson->getId() . '/edit'
        );
        self::assertResponseIsSuccessful();

        $options = $this->getSelectOptionTexts($crawler, 'select[name="lesson[cursus]"] option');

        self::assertContains(
            'Cursus d’initiation à la guitare',
            $options,
            'Le cursus courant inactif doit rester sélectionnable en édition.'
        );
    }

    public function testThemeCursusAndLessonCanBeArchivedAndReactivatedWithExpectedMessagesAndFilters(): void
    {
        $this->loginAsAdmin();

        $theme = $this->getThemeByName('Musique');
        $cursus = $this->getCursusByName('Cursus d’initiation à la guitare');
        $lesson = $this->getLessonByTitle('Découverte de l’instrument');

        // =========================
        // Archivage thème
        // =========================
        $crawler = $this->client->request(
            'GET',
            'https://localhost/admin/themes/' . $theme->getId() . '/delete'
        );
        self::assertResponseIsSuccessful();

        $themeDisableToken = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/themes/%d/disable"]', $theme->getId())
        );

        $this->client->request(
            'POST',
            'https://localhost/admin/themes/' . $theme->getId() . '/disable',
            ['_token' => $themeDisableToken]
        );

        self::assertResponseRedirects('/admin/themes');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Thème désactivé.');

        $this->em->clear();
        $theme = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertNotNull($theme);
        self::assertFalse($theme->isActive());

        $this->client->request('GET', 'https://localhost/admin/themes?status=archived');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Musique', $this->client->getResponse()->getContent() ?? '');

        $this->client->request('GET', 'https://localhost/admin/themes?status=active');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Musique', $this->client->getResponse()->getContent() ?? '');

        // =========================
        // Archivage cursus
        // =========================
        $crawler = $this->client->request(
            'GET',
            'https://localhost/admin/cursus/' . $cursus->getId() . '/delete'
        );
        self::assertResponseIsSuccessful();

        $cursusDisableToken = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/cursus/%d/disable"]', $cursus->getId())
        );

        $this->client->request(
            'POST',
            'https://localhost/admin/cursus/' . $cursus->getId() . '/disable',
            ['_token' => $cursusDisableToken]
        );

        self::assertResponseRedirects('/admin/cursus');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Cursus archivé.');

        $this->em->clear();
        $cursus = $this->em->getRepository(Cursus::class)->find($cursus->getId());
        self::assertNotNull($cursus);
        self::assertFalse($cursus->isActive());

        $this->client->request('GET', 'https://localhost/admin/cursus?status=archived');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'Cursus d’initiation à la guitare',
            $this->client->getResponse()->getContent() ?? ''
        );

        $this->client->request('GET', 'https://localhost/admin/cursus?status=active');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            'Cursus d’initiation à la guitare',
            $this->client->getResponse()->getContent() ?? ''
        );

        // =========================
        // Archivage leçon
        // =========================
        $crawler = $this->client->request(
            'GET',
            'https://localhost/admin/lesson/' . $lesson->getId() . '/delete'
        );
        self::assertResponseIsSuccessful();

        $lessonDisableToken = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/lesson/%d/disable"]', $lesson->getId())
        );

        $this->client->request(
            'POST',
            'https://localhost/admin/lesson/' . $lesson->getId() . '/disable',
            ['_token' => $lessonDisableToken]
        );

        self::assertResponseRedirects('/admin/lesson');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Leçon archivée.');

        $this->em->clear();
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());
        self::assertNotNull($lesson);
        self::assertFalse($lesson->isActive());

        $this->client->request('GET', 'https://localhost/admin/lesson?status=archived');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'Découverte de l’instrument',
            $this->client->getResponse()->getContent() ?? ''
        );

        $this->client->request('GET', 'https://localhost/admin/lesson?status=active');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            'Découverte de l’instrument',
            $this->client->getResponse()->getContent() ?? ''
        );

        // =========================
        // Réactivation thème
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes?status=archived');
        self::assertResponseIsSuccessful();

        $themeActivateToken = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/themes/%d/activate"]', $theme->getId())
        );

        $this->client->request(
            'POST',
            'https://localhost/admin/themes/' . $theme->getId() . '/activate',
            ['_token' => $themeActivateToken]
        );

        self::assertResponseRedirects('/admin/themes');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Thème réactivé.');

        $this->em->clear();
        $theme = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertNotNull($theme);
        self::assertTrue($theme->isActive());

        // =========================
        // Réactivation cursus
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus?status=archived');
        self::assertResponseIsSuccessful();

        $cursusActivateToken = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/cursus/%d/activate"]', $cursus->getId())
        );

        $this->client->request(
            'POST',
            'https://localhost/admin/cursus/' . $cursus->getId() . '/activate',
            ['_token' => $cursusActivateToken]
        );

        self::assertResponseRedirects('/admin/cursus');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Cursus réactivé.');

        $this->em->clear();
        $cursus = $this->em->getRepository(Cursus::class)->find($cursus->getId());
        self::assertNotNull($cursus);
        self::assertTrue($cursus->isActive());

        // =========================
        // Réactivation leçon
        // =========================
        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson?status=archived');
        self::assertResponseIsSuccessful();

        $lessonActivateToken = $this->extractCsrfTokenFromCrawler(
            $crawler,
            sprintf('form[action="/admin/lesson/%d/activate"]', $lesson->getId())
        );

        $this->client->request(
            'POST',
            'https://localhost/admin/lesson/' . $lesson->getId() . '/activate',
            ['_token' => $lessonActivateToken]
        );

        self::assertResponseRedirects('/admin/lesson');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-success', 'Leçon restaurée.');

        $this->em->clear();
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());
        self::assertNotNull($lesson);
        self::assertTrue($lesson->isActive());

        // =========================
        // Retour dans les listes actives
        // =========================
        $this->client->request('GET', 'https://localhost/admin/themes?status=active');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Musique', $this->client->getResponse()->getContent() ?? '');

        $this->client->request('GET', 'https://localhost/admin/cursus?status=active');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'Cursus d’initiation à la guitare',
            $this->client->getResponse()->getContent() ?? ''
        );

        $this->client->request('GET', 'https://localhost/admin/lesson?status=active');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString(
            'Découverte de l’instrument',
            $this->client->getResponse()->getContent() ?? ''
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}