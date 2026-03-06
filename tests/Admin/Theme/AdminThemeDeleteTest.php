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

    private function loginAsUser(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture not found.');
        $this->client->loginUser($user);
    }

    private function getThemeByName(string $name): Theme
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => $name]);
        self::assertNotNull($theme, sprintf('Theme "%s" not found.', $name));

        return $theme;
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

    public function testDeleteConfirmPageIsOkAndDoesNotChangeTheme(): void
    {
        $this->loginAsAdmin();

        $theme = $this->getThemeByName('Musique');
        $theme->setIsActive(true);
        $theme->setDescription('Description de test');
        $this->em->flush();

        $id = $theme->getId();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/' . $id . '/delete');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Désactiver un thème');
        self::assertSelectorTextContains('h2.theme-detail-title', $theme->getName());
        self::assertSelectorExists('a.btn.btn-secondary[href="/admin/themes"]');
        self::assertSelectorExists(sprintf('form[action="/admin/themes/%d/disable"][method="post"]', $id));
        self::assertSelectorTextContains('button.btn.btn-danger', 'Confirmer la désactivation');
        self::assertSelectorExists('.admin-alert');
        self::assertSelectorTextContains('.admin-alert', 'Ce thème sera désactivé');

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Description de test', $content);

        $this->extractHiddenToken(
            $crawler,
            sprintf('form[action="/admin/themes/%d/disable"][method="post"]', $id)
        );

        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive(), 'Theme should still be active after GET confirmation page.');
    }

    public function testDeleteConfirmPageWithoutDescriptionDoesNotCrash(): void
    {
        $this->loginAsAdmin();

        $theme = $this->getThemeByName('Musique');
        $theme->setDescription(null);
        $this->em->flush();

        $this->client->request('GET', 'https://localhost/admin/themes/' . $theme->getId() . '/delete');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.admin-page-title', 'Désactiver un thème');
    }

    public function testDeletePageRequiresAdminRole(): void
    {
        $theme = $this->getThemeByName('Musique');

        $this->client->request(
            'GET',
            'https://localhost/admin/themes/' . $theme->getId() . '/delete'
        );
        self::assertResponseRedirects('/login');

        $this->loginAsUser();

        $this->client->request(
            'GET',
            'https://localhost/admin/themes/' . $theme->getId() . '/delete'
        );
        self::assertResponseStatusCodeSame(403);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}