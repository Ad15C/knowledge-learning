<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AdminThemeControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        // ✅ Force HTTPS pour satisfaire requires_channel: https sur /admin
        $this->client = static::createClient([], [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SERVER_PORT' => 443,
        ]);

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
        $this->client->loginUser($admin, 'main');
    }

    private function loginAsUser(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture not found.');
        $this->client->loginUser($user, 'main');
    }

    private function findThemeByName(string $name): Theme
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => $name]);
        self::assertNotNull($theme, sprintf('Theme "%s" not found in fixtures.', $name));
        return $theme;
    }

    private function extractTokenForFormAction(Crawler $crawler, string $actionPath): string
    {
        $tokenNode = $crawler->filter(sprintf('form[action="%s"] input[name="_token"]', $actionPath));
        self::assertGreaterThan(
            0,
            $tokenNode->count(),
            sprintf('CSRF token not found for form[action="%s"]', $actionPath)
        );

        return (string) $tokenNode->first()->attr('value');
    }

    public function testAdminAreaRequiresAdminRole(): void
    {
        // Non loggé => redirection vers login (souvent 302) ou 403 selon config
        $this->client->request('GET', '/admin/themes');
        self::assertTrue(
            $this->client->getResponse()->isRedirection() ||
            $this->client->getResponse()->getStatusCode() === 403
        );

        // ROLE_USER => 403
        $this->loginAsUser();
        $this->client->request('GET', '/admin/themes');
        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexIsReachableForAdmin(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin/themes');
        self::assertResponseIsSuccessful();
    }

    public function testNewPageIsReachableForAdmin(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin/themes/new');
        self::assertResponseIsSuccessful();
    }

    public function testNewPostCreatesThemeAndRedirects(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', '/admin/themes/new');
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

        $theme = $this->findThemeByName('Musique');

        $this->client->request('GET', '/admin/themes/'.$theme->getId().'/edit');
        self::assertResponseIsSuccessful();
    }

    public function testEditPostUpdatesThemeAndRedirects(): void
    {
        $this->loginAsAdmin();

        $theme = $this->findThemeByName('Musique');

        $crawler = $this->client->request('GET', '/admin/themes/'.$theme->getId().'/edit');
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

        $theme = $this->findThemeByName('Musique');

        $this->client->request('GET', '/admin/themes/'.$theme->getId().'/delete');
        self::assertResponseIsSuccessful();
    }

    public function testDisableRequiresValidCsrfAndDisablesTheme(): void
    {
        $this->loginAsAdmin();

        $theme = $this->findThemeByName('Informatique');
        $theme->setIsActive(true);
        $this->em->flush();

        // 1) Aller sur la page de confirmation (doit contenir le form POST /disable)
        $crawler = $this->client->request('GET', '/admin/themes/'.$theme->getId().'/delete');
        self::assertResponseIsSuccessful();

        $action = '/admin/themes/'.$theme->getId().'/disable';

        // 2) Vérifie que le formulaire disable existe sur la page
        $formNode = $crawler->filter(sprintf('form[action="%s"]', $action));
        self::assertGreaterThan(0, $formNode->count(), 'Disable form not found on delete confirmation page.');

        // 3) Test CSRF invalide => 403 (on prend le même form et on remplace le token)
        $badForm = $formNode->first()->form();
        $badForm->setValues(['_token' => 'bad']);

        $this->client->submit($badForm);
        self::assertResponseStatusCodeSame(403);

        // 4) Soumission avec le vrai token présent dans le HTML => redirect
        $crawler = $this->client->request('GET', '/admin/themes/'.$theme->getId().'/delete');
        self::assertResponseIsSuccessful();

        $goodForm = $crawler->filter(sprintf('form[action="%s"]', $action))->first()->form();
        $this->client->submit($goodForm);

        self::assertResponseRedirects('/admin/themes');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertFalse($reloaded->isActive());
    }

    public function testActivateRequiresValidCsrfAndActivatesTheme(): void
    {
        $this->loginAsAdmin();

        $theme = $this->findThemeByName('Jardinage');
        $theme->setIsActive(false);
        $this->em->flush();

        // CSRF invalide => 403
        $this->client->request('POST', '/admin/themes/'.$theme->getId().'/activate', [
            '_token' => 'bad',
        ]);
        self::assertResponseStatusCodeSame(403);

        // Token présent sur la liste archived (si ton twig affiche le bouton)
        $crawler = $this->client->request('GET', '/admin/themes?status=archived');
        self::assertResponseIsSuccessful();

        $action = sprintf('/admin/themes/%d/activate', $theme->getId());
        $token = $this->extractTokenForFormAction($crawler, $action);

        $this->client->request('POST', $action, [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/admin/themes');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertTrue($reloaded->isActive());
    }
}