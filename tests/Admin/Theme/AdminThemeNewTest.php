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

class AdminThemeNewTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get()
            ->loadFixtures([
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

    public function testGetNewDisplaysForm(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Créer un thème');
        self::assertSelectorExists('.admin-page-header');
        self::assertSelectorExists('a.btn.btn-secondary[href="/admin/themes"]');

        self::assertGreaterThan(0, $crawler->filter('form')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="theme[name]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('textarea[name="theme[description]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="theme[image]"]')->count());

        self::assertGreaterThan(0, $crawler->selectButton('Créer')->count());
    }

    public function testPostNewValidCreatesThemeIsActiveTrueFlashesAndRedirects(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer')->form([
            'theme[name]' => 'Nouveau Thème Test',
            'theme[description]' => 'Une description',
            'theme[image]' => '',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/themes');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Thème créé.');

        $this->em->clear();

        /** @var Theme|null $created */
        $created = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Nouveau Thème Test']);
        self::assertNotNull($created);
        self::assertTrue($created->isActive());
        self::assertSame('Une description', $created->getDescription());
        self::assertNull($created->getImage());
    }

    public function testPostNewEmptyNameShowsFormErrorAndDoesNotCreate(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer')->form([
            'theme[name]' => '',
            'theme[description]' => '',
            'theme[image]' => '',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $html = (string) $this->client->getResponse()->getContent();

        self::assertTrue(
            str_contains($html, 'Le nom est obligatoire')
            || str_contains($html, 'This value should not be blank')
            || str_contains($html, 'Cette valeur ne doit pas être vide')
        );

        $this->em->clear();
        $created = $this->em->getRepository(Theme::class)->findOneBy(['name' => '']);
        self::assertNull($created);
    }

    public function testDescriptionAndImageCanBeEmpty(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer')->form([
            'theme[name]' => 'Theme Sans Desc Ni Image',
            'theme[description]' => '',
            'theme[image]' => '',
        ]);

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/themes');

        $this->em->clear();

        /** @var Theme|null $created */
        $created = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Theme Sans Desc Ni Image']);
        self::assertNotNull($created);
        self::assertTrue($created->isActive());
        self::assertNull($created->getDescription());
        self::assertNull($created->getImage());
    }

    public function testHtmlInjectionInDescriptionIsEscapedOnIndex(): void
    {
        $this->loginAsAdmin();

        $payload = '<script>alert("xss")</script><b>bold</b>';

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer')->form([
            'theme[name]' => 'Theme XSS',
            'theme[description]' => $payload,
            'theme[image]' => '',
        ]);

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/themes');

        $this->client->request('GET', 'https://localhost/admin/themes?q=XSS');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('<script>alert("xss")</script>', $html);
        self::assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $html);

        self::assertStringNotContainsString('<b>bold</b>', $html);
        self::assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}