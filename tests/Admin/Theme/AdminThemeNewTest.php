<?php

namespace App\Tests\Controller\Admin\Theme;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Theme;
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
        $admin = $this->em->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture not found. Fixtures not loaded?');
        $this->client->loginUser($admin);
    }

    public function testGetNewDisplaysForm(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        // Le form existe et contient les champs
        self::assertGreaterThan(0, $crawler->filter('form')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="theme[name]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('textarea[name="theme[description]"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="theme[image]"]')->count());

        // Bouton submit "Créer"
        self::assertGreaterThan(0, $crawler->filter('button[type="submit"]')->count());
    }

    public function testPostNewValidCreatesThemeIsActiveTrueFlashesAndRedirects(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();

        $form['theme[name]'] = 'Nouveau Thème Test';
        $form['theme[description]'] = 'Une description';
        $form['theme[image]'] = ''; // accepté

        $this->client->submit($form);

        // Redirect vers la liste
        self::assertResponseRedirects('/admin/themes');

        // Suivre la redirection pour vérifier le flash et l'affichage
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        // Flash success (texte exact dans controller: "Thème créé.")
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Thème créé.', $html);

        // Vérifie en base
        $created = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Nouveau Thème Test']);
        self::assertNotNull($created);

        // isActive doit être true (défaut controller + entity)
        self::assertTrue($created->isActive());
    }

    public function testPostNewEmptyNameShowsFormErrorAndDoesNotCreate(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();
        $form['theme[name]'] = '';
        $form['theme[description]'] = '';
        $form['theme[image]'] = '';

        $this->client->submit($form);

        // plus de 500, on reste sur la page
        self::assertResponseStatusCodeSame(200);

        $html = (string) $this->client->getResponse()->getContent();

        // message de validation (FR si tu as mis le message)
        self::assertTrue(
            str_contains($html, 'Le nom est obligatoire') ||
            str_contains($html, 'This value should not be blank')
        );

        // aucun thème créé
        $created = $this->em->getRepository(Theme::class)->findOneBy(['name' => '']);
        self::assertNull($created);
    }

    public function testDescriptionAndImageCanBeEmpty(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();
        $form['theme[name]'] = 'Theme Sans Desc Ni Image';
        $form['theme[description]'] = '';
        $form['theme[image]'] = '';

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/themes');

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

        // Crée via le form
        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();
        $form['theme[name]'] = 'Theme XSS';
        $form['theme[description]'] = $payload;
        $form['theme[image]'] = '';

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/themes');

        // Va sur l'index et vérifie l'escape Twig
        $this->client->request('GET', 'https://localhost/admin/themes?q=XSS');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();

        // Le script ne doit pas apparaître tel quel
        self::assertStringNotContainsString('<script>alert("xss")</script>', $html);

        // Mais la version échappée doit apparaître (au moins "<script" devient "&lt;script")
        self::assertStringContainsString('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $html);

        // Le <b> doit aussi être échappé (donc pas de balise <b> réelle)
        self::assertStringNotContainsString('<b>bold</b>', $html);
        self::assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
    }
}