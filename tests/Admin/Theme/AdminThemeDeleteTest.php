<?php

namespace App\Tests\Controller\Admin\Theme;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminThemeDeleteTest extends WebTestCase
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

        self::assertNotNull($admin, 'Admin fixture not found. Fixtures not loaded?');
        $this->client->loginUser($admin);
    }

    public function testDeleteConfirmPageIsOkAndDoesNotChangeTheme(): void
    {
        $this->loginAsAdmin();

        // Prends un thème actif
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        // On force actif pour que le test soit stable
        $theme->setIsActive(true);
        $this->em->flush();

        $id = $theme->getId();

        // GET confirmation
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/'.$id.'/delete');
        self::assertResponseIsSuccessful();

        // Contenu page
        self::assertSelectorTextContains('h1', 'Archiver un thème');
        self::assertSelectorTextContains('h2.theme-detail-title', $theme->getName());

        // Le form POST doit cibler /admin/themes/{id}/disable
        self::assertSelectorExists(sprintf('form[action="/admin/themes/%d/disable"][method="post"]', $id));

        // Token CSRF présent
        $tokenInput = $crawler->filter('form input[name="_token"]')->first();
        self::assertGreaterThan(0, $tokenInput->count(), 'CSRF token input not found.');
        self::assertNotEmpty((string) $tokenInput->attr('value'));

        // Aucun effet sur la DB : GET ne doit pas archiver/supprimer
        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive(), 'Theme should still be active after GET confirmation page.');
    }

    private function loginAsUser(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture not found.');
        $this->client->loginUser($user);
    }

    public function testDeletePageRequiresAdminRole(): void
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        // Non loggé
        $this->client->request(
            'GET',
            'https://localhost/admin/themes/'.$theme->getId().'/delete'
        );

        self::assertTrue(
            $this->client->getResponse()->isRedirection()
            || $this->client->getResponse()->getStatusCode() === 403
        );

        // ROLE_USER
        $this->loginAsUser();

        $this->client->request(
            'GET',
            'https://localhost/admin/themes/'.$theme->getId().'/delete'
        );

        self::assertResponseStatusCodeSame(403);
    }
}