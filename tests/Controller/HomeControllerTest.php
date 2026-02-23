<?php

namespace App\Tests\Controller;

use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        // Nettoyage base avant chaque test
        $this->em->createQuery('DELETE FROM App\Entity\Theme')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    private function createThemes(): void
    {
        $t1 = (new Theme())
            ->setName('Symfony')
            ->setDescription('Framework PHP')
            ->setImage('images/symfony.png');

        $t2 = (new Theme())
            ->setName('Docker')
            ->setDescription('Container')
            ->setImage('images/docker.png');

        $this->em->persist($t1);
        $this->em->persist($t2);
        $this->em->flush();
    }

    private function createUser(string $role = 'ROLE_USER'): User
    {
        $user = (new User())
            ->setEmail('user@test.com')
            ->setPassword('password')
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setRoles([$role])
            ->setIsVerified(true);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** PAGE ACCESSIBLE */
    public function testHomepageLoads(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Bienvenue sur Knowledge Learning');
    }

    /** THEMES AFFICHÉS */
    public function testThemesDisplayed(): void
    {
        $this->createThemes();
        $crawler = $this->client->request('GET', '/');
        $this->assertCount(2, $crawler->filter('.theme-card'));
    }

    /** AUCUN THEME */
    public function testNoThemesMessage(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertSelectorTextContains('body', 'Aucun thème disponible');
    }

    /** LIENS THEMES */
    public function testThemeLinks(): void
    {
        $this->createThemes();
        $crawler = $this->client->request('GET', '/');

        $links = $crawler->filter('.theme-card a.btn');
        $this->assertGreaterThan(0, $links->count());

        foreach ($links as $link) {
            $this->assertStringContainsString('/themes/', $link->getAttribute('href'));
        }
    }

    /** MENU VISITEUR */
    public function testVisitorMenu(): void
    {
        $this->client->request('GET', '/');
        $this->assertSelectorExists('a[href="/login"]');
        $this->assertSelectorExists('a[href="/register"]');
    }

    /** MENU USER CONNECTÉ */
    public function testUserMenu(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/');

        // URL générée dynamiquement pour être sûre
        $urlDashboard = self::getContainer()->get('router')->generate('user_dashboard');
        $this->assertSelectorExists('a[href="' . $urlDashboard . '"]');

        $urlLogout = self::getContainer()->get('router')->generate('app_logout');
        $this->assertSelectorExists('a[href="' . $urlLogout . '"]');
    }

    /** MENU ADMIN */
    /*public function testAdminMenu(): void
    {
        $admin = $this->createUser('ROLE_ADMIN');
        $this->client->loginUser($admin);
        $this->client->request('GET', '/');
        $this->assertSelectorTextContains('body', 'Admin');
    } */

    /** PANIER AFFICHÉ */
    public function testCartVisible(): void
    {
        $this->client->request('GET', '/');
        $this->assertSelectorExists('.menu-badge');
    }

    /** PERFORMANCE */
    public function testHomepagePerformance(): void
    {
        $start = microtime(true);
        $this->client->request('GET', '/');
        $time = microtime(true) - $start;
        $this->assertLessThan(1, $time, 'Homepage too slow');
    }
}