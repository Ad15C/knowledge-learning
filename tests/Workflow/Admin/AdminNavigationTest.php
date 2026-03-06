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

class AdminNavigationTest extends WebTestCase
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

    private function getUser(): User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture introuvable.');

        return $user;
    }

    private function getThemeFixture(): Theme
    {
        $theme = $this->em->getRepository(Theme::class)
            ->findOneBy(['name' => 'Musique']);

        self::assertNotNull($theme, 'Thème fixture introuvable.');

        return $theme;
    }

    private function getCursusFixture(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)
            ->findOneBy(['name' => 'Cursus d’initiation à la guitare']);

        self::assertNotNull($cursus, 'Cursus fixture introuvable.');

        return $cursus;
    }

    private function getLessonFixture(): Lesson
    {
        $lesson = $this->em->getRepository(Lesson::class)
            ->findOneBy(['title' => 'Découverte de l’instrument']);

        self::assertNotNull($lesson, 'Leçon fixture introuvable.');

        return $lesson;
    }

    public function testAdminDashboardAndSidebarNavigationAreReachable(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

        $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bienvenue');

        // Dashboard cards / main links
        self::assertSelectorExists('a[href="/admin/users?status=active"]');
        self::assertSelectorExists('a[href="/admin/themes"]');
        self::assertSelectorExists('a[href="/admin/themes/new"]');
        self::assertSelectorExists('a[href="/admin/cursus"]');
        self::assertSelectorExists('a[href="/admin/cursus/new"]');
        self::assertSelectorExists('a[href="/admin/lesson"]');
        self::assertSelectorExists('a[href="/admin/lesson/new"]');
        self::assertSelectorExists('a[href="/admin/purchases"]');
        self::assertSelectorExists('a[href="/admin/contact/"]');

        // Sidebar links
        self::assertSelectorExists('.sidebar-link[href="/admin"]');
        self::assertSelectorExists('.sidebar-link[href="/admin/users"]');
        self::assertSelectorExists('.sidebar-link[href="/admin/themes"]');
        self::assertSelectorExists('.sidebar-link[href="/admin/cursus"]');
        self::assertSelectorExists('.sidebar-link[href="/admin/lesson"]');
        self::assertSelectorExists('.sidebar-link[href="/admin/purchases"]');
        self::assertSelectorExists('.sidebar-link[href="/admin/contact/"]');
    }

    public function testAdminCanReachMainAdminListPages(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

        $pages = [
            'https://localhost/admin',
            'https://localhost/admin/users',
            'https://localhost/admin/themes',
            'https://localhost/admin/cursus',
            'https://localhost/admin/lesson',
            'https://localhost/admin/purchases',
            'https://localhost/admin/contact/',
        ];

        foreach ($pages as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful(sprintf('La page "%s" devrait être accessible.', $url));
        }
    }

    public function testAdminCanReachMainCreationPages(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

        $pages = [
            'https://localhost/admin/themes/new',
            'https://localhost/admin/cursus/new',
            'https://localhost/admin/lesson/new',
        ];

        foreach ($pages as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful(sprintf('La page "%s" devrait être accessible.', $url));
        }
    }

    public function testAdminCanReachThemePages(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
        $theme = $this->getThemeFixture();

        $this->client->request('GET', 'https://localhost/admin/themes');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', 'https://localhost/admin/themes/' . $theme->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', 'https://localhost/admin/themes/' . $theme->getId() . '/delete');
        self::assertResponseIsSuccessful();
    }

    public function testAdminCanReachCursusPages(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
        $cursus = $this->getCursusFixture();

        $this->client->request('GET', 'https://localhost/admin/cursus');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/delete');
        self::assertResponseIsSuccessful();
    }

    public function testAdminCanReachLessonPages(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
        $lesson = $this->getLessonFixture();

        $this->client->request('GET', 'https://localhost/admin/lesson');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', 'https://localhost/admin/lesson/' . $lesson->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', 'https://localhost/admin/lesson/' . $lesson->getId() . '/delete');
        self::assertResponseIsSuccessful();
    }

    public function testAdminCanNavigateFromDashboardUsingVisibleLinks(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

        $crawler = $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->clickLink('Liste des thèmes');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Thèmes');

        $crawler = $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->clickLink('Créer un nouveau thème');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer un thème');

        $crawler = $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->clickLink('Liste des cursus');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Cursus');

        $crawler = $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->clickLink('Créer un nouveau cursus');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer un cursus');

        $crawler = $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->clickLink('Catalogue des leçons');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Leçons');

        $crawler = $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->clickLink('Ajouter une leçon');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Créer une leçon');

        $crawler = $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->clickLink('Toutes les commandes');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', 'https://localhost/admin');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->clickLink('Tous les messages');
        self::assertResponseIsSuccessful();
    }

    public function testAnonymousIsRedirectedToLoginOnAdminPages(): void
    {
        $theme = $this->getThemeFixture();
        $cursus = $this->getCursusFixture();
        $lesson = $this->getLessonFixture();

        $urls = [
            'https://localhost/admin',
            'https://localhost/admin/users',
            'https://localhost/admin/themes',
            'https://localhost/admin/themes/new',
            'https://localhost/admin/themes/' . $theme->getId() . '/edit',
            'https://localhost/admin/themes/' . $theme->getId() . '/delete',
            'https://localhost/admin/cursus',
            'https://localhost/admin/cursus/new',
            'https://localhost/admin/cursus/' . $cursus->getId() . '/edit',
            'https://localhost/admin/cursus/' . $cursus->getId() . '/delete',
            'https://localhost/admin/lesson',
            'https://localhost/admin/lesson/new',
            'https://localhost/admin/lesson/' . $lesson->getId() . '/edit',
            'https://localhost/admin/lesson/' . $lesson->getId() . '/delete',
            'https://localhost/admin/purchases',
            'https://localhost/admin/contact/',
        ];

        foreach ($urls as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/login');
        }
    }

    public function testUserIsForbiddenOnAdminPages(): void
    {
        $this->client->loginUser($this->getUser(), 'main');

        $theme = $this->getThemeFixture();
        $cursus = $this->getCursusFixture();
        $lesson = $this->getLessonFixture();

        $urls = [
            'https://localhost/admin',
            'https://localhost/admin/users',
            'https://localhost/admin/themes',
            'https://localhost/admin/themes/new',
            'https://localhost/admin/themes/' . $theme->getId() . '/edit',
            'https://localhost/admin/themes/' . $theme->getId() . '/delete',
            'https://localhost/admin/cursus',
            'https://localhost/admin/cursus/new',
            'https://localhost/admin/cursus/' . $cursus->getId() . '/edit',
            'https://localhost/admin/cursus/' . $cursus->getId() . '/delete',
            'https://localhost/admin/lesson',
            'https://localhost/admin/lesson/new',
            'https://localhost/admin/lesson/' . $lesson->getId() . '/edit',
            'https://localhost/admin/lesson/' . $lesson->getId() . '/delete',
            'https://localhost/admin/purchases',
            'https://localhost/admin/contact/',
        ];

        foreach ($urls as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}