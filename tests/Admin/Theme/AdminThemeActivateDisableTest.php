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
use Symfony\Component\DomCrawler\Crawler;

class AdminThemeActivateDisableTest extends WebTestCase
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

    private function extractCsrfTokenFromCrawler(Crawler $crawler, string $formSelector = 'form'): string
    {
        $tokenNode = $crawler->filter($formSelector.' input[name="_token"]')->first();
        self::assertGreaterThan(0, $tokenNode->count(), 'CSRF token input not found.');
        $token = (string) $tokenNode->attr('value');
        self::assertNotEmpty($token, 'CSRF token value is empty.');
        return $token;
    }

    private function getDisableTokenForTheme(int $themeId): string
    {
        // Token disable présent sur la page GET /delete
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/'.$themeId.'/delete');
        self::assertResponseIsSuccessful();

        $formSelector = sprintf('form[action="/admin/themes/%d/disable"][method="post"]', $themeId);
        self::assertSelectorExists($formSelector);

        return $this->extractCsrfTokenFromCrawler($crawler, $formSelector);
    }

    private function getActivateTokenForThemeFromArchivedList(int $themeId): string
    {
        // Token activate présent sur la page listant les archivés
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes?status=archived');
        self::assertResponseIsSuccessful();

        $formSelector = sprintf('form[action="/admin/themes/%d/activate"]', $themeId);
        self::assertSelectorExists($formSelector);

        return $this->extractCsrfTokenFromCrawler($crawler, $formSelector);
    }

    public function testDisableWithInvalidCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Informatique']);
        self::assertNotNull($theme);

        $this->client->request('POST', 'https://localhost/admin/themes/'.$theme->getId().'/disable', [
            '_token' => 'bad',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisableWithValidCsrfDisablesThemeRedirectsAndFlashes(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Informatique']);
        self::assertNotNull($theme);

        $theme->setIsActive(true);
        $this->em->flush();

        $id = $theme->getId();
        $token = $this->getDisableTokenForTheme($id);

        $this->client->request('POST', 'https://localhost/admin/themes/'.$id.'/disable', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/themes');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Thème désactivé.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
    }

    public function testDisableAlreadyDisabledIsIdempotent(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Informatique']);
        self::assertNotNull($theme);

        $theme->setIsActive(false);
        $this->em->flush();

        $id = $theme->getId();
        $token = $this->getDisableTokenForTheme($id);

        $this->client->request('POST', 'https://localhost/admin/themes/'.$id.'/disable', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/themes');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Thème désactivé.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
    }

    public function testActivateWithInvalidCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Jardinage']);
        self::assertNotNull($theme);

        $this->client->request('POST', 'https://localhost/admin/themes/'.$theme->getId().'/activate', [
            '_token' => 'bad',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivateWithValidCsrfActivatesThemeRedirectsAndFlashes(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Jardinage']);
        self::assertNotNull($theme);

        // On veut activer un thème archivé => on force false pour qu'il apparaisse dans status=archived
        $theme->setIsActive(false);
        $this->em->flush();

        $id = $theme->getId();
        $token = $this->getActivateTokenForThemeFromArchivedList($id);

        $this->client->request('POST', 'https://localhost/admin/themes/'.$id.'/activate', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/themes');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Thème réactivé.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive());
    }

    public function testActivateAlreadyActiveIsIdempotent(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Jardinage']);
        self::assertNotNull($theme);

        $id = $theme->getId();

        // On veut tester "activer alors que déjà actif"
        $theme->setIsActive(true);
        $this->em->flush();

        // Astuce : on passe temporairement en archived pour récupérer le token depuis l'UI,
        // puis on repasse en active AVANT l'appel POST.
        $theme->setIsActive(false);
        $this->em->flush();

        $token = $this->getActivateTokenForThemeFromArchivedList($id);

        // Revenir à actif => le POST /activate devient idempotent
        $theme = $this->em->getRepository(Theme::class)->find($id);
        $theme->setIsActive(true);
        $this->em->flush();

        $this->client->request('POST', 'https://localhost/admin/themes/'.$id.'/activate', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/themes');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Thème réactivé.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive());
    }
}