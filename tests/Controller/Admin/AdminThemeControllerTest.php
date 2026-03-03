<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminThemeControllerTest extends WebTestCase
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
        $admin = $this->em->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture not found. Fixtures not loaded?');
        $this->client->loginUser($admin);
    }

    private function loginAsUser(): void
    {
        $user = $this->em->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture not found. Fixtures not loaded?');
        $this->client->loginUser($user);
    }

    private function extractCsrfTokenFromCrawler(\Symfony\Component\DomCrawler\Crawler $crawler): string
    {
        $node = $crawler->filter('input[name="_token"]')->first();
        self::assertGreaterThan(0, $node->count(), 'CSRF token input not found in form');
        return (string) $node->attr('value');
    }

    public function testAdminAreaRequiresAdminRole(): void
    {
        // Non loggé => redirect login ou 403
        $this->client->request('GET', 'https://localhost/admin/themes');
        self::assertTrue(
            $this->client->getResponse()->isRedirection() || $this->client->getResponse()->getStatusCode() === 403
        );

        // ROLE_USER => 403
        $this->loginAsUser();
        $this->client->request('GET', 'https://localhost/admin/themes');
        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexIsReachableForAdmin(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/themes');
        self::assertResponseIsSuccessful();
    }

    public function testNewPageIsReachableForAdmin(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();
    }

    public function testNewPostCreatesThemeAndRedirects(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();
        $form['theme[name]'] = 'Theme Test Create';
        $form['theme[description]'] = 'Desc test';
        $form['theme[image]'] = '';

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/themes');

        $created = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Theme Test Create']);
        self::assertNotNull($created);
        self::assertTrue($created->isActive());
    }

    public function testEditPageIsReachableForAdmin(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        $this->client->request('GET', 'https://localhost/admin/themes/'.$theme->getId().'/edit');
        self::assertResponseIsSuccessful();
    }

    public function testEditPostUpdatesThemeAndRedirects(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/'.$theme->getId().'/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();
        $form['theme[name]'] = 'Musique (modifiée)';
        $form['theme[description]'] = 'Nouvelle description';
        $form['theme[image]'] = '';

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/themes');

        $this->em->clear();
        $updated = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertSame('Musique (modifiée)', $updated->getName());
    }

    public function testDeleteConfirmPageIsReachableForAdmin(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        $this->client->request('GET', 'https://localhost/admin/themes/'.$theme->getId().'/delete');
        self::assertResponseIsSuccessful();
    }

    public function testDisableRequiresValidCsrfAndDisablesTheme(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Informatique']);
        self::assertNotNull($theme);

        $theme->setIsActive(true);
        $this->em->flush();

        // CSRF invalide => 403
        $this->client->request('POST', 'https://localhost/admin/themes/'.$theme->getId().'/disable', [
            '_token' => 'bad',
        ]);
        self::assertResponseStatusCodeSame(403);

        // On récupère le token depuis la page de confirmation delete
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/'.$theme->getId().'/delete');
        self::assertResponseIsSuccessful();

        $token = $this->extractCsrfTokenFromCrawler($crawler);

        $this->client->request('POST', 'https://localhost/admin/themes/'.$theme->getId().'/disable', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/admin/themes');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertFalse($reloaded->isActive());
    }

    public function testActivateRequiresValidCsrfAndActivatesTheme(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Jardinage']);
        self::assertNotNull($theme);

        $theme->setIsActive(false);
        $this->em->flush();

        // CSRF invalide => 403
        $this->client->request('POST', 'https://localhost/admin/themes/'.$theme->getId().'/activate', [
            '_token' => 'bad',
        ]);
        self::assertResponseStatusCodeSame(403);

        // Page listant les archivés => contient le form POST /activate avec le token
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes?status=archived');
        self::assertResponseIsSuccessful();

        // On prend le formulaire qui pointe vers l'action activate pour cet id
        $formNode = $crawler->filter(sprintf('form[action="/admin/themes/%d/activate"] input[name="_token"]', $theme->getId()));
        self::assertGreaterThan(0, $formNode->count(), 'Activate form/token not found on archived list');

        $token = (string) $formNode->attr('value');

        $this->client->request('POST', 'https://localhost/admin/themes/'.$theme->getId().'/activate', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/admin/themes');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertTrue($reloaded->isActive());
    }
}