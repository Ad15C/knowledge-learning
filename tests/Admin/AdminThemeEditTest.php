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

class AdminThemeEditTest extends WebTestCase
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

    public function testEditGetShowsPrefilledFields(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        $theme->setDescription('Desc avant edit');
        $theme->setImage('/img-avant.jpg');
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/'.$theme->getId().'/edit');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1', 'Modifier : '.$theme->getName());

        $form = $crawler->filter('form')->first()->form();
        self::assertSame('Musique', $form['theme[name]']->getValue());
        self::assertSame('Desc avant edit', $form['theme[description]']->getValue());
        self::assertSame('/img-avant.jpg', $form['theme[image]']->getValue());
    }

    public function testEditPostUpdatesThemePersistsAndShowsFlash(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/'.$theme->getId().'/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'theme[name]' => 'Musique (modifiée)',
            'theme[description]' => 'Nouvelle description',
            'theme[image]' => '/img-new.jpg',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/themes');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        // Flash rendu par base.html.twig
        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Thème modifié.');

        // Reload DB
        $id = $theme->getId();
        $this->em->clear();

        $reloaded = $this->em->getRepository(Theme::class)->find($id);
        self::assertNotNull($reloaded);

        self::assertSame('Musique (modifiée)', $reloaded->getName());
        self::assertSame('Nouvelle description', $reloaded->getDescription());
        self::assertSame('/img-new.jpg', $reloaded->getImage());
    }

    public function testEditPostWithInvalidDataShowsErrorsAndDoesNotPersist(): void
    {
        $this->loginAsAdmin();

        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        $id = $theme->getId();
        $originalName = $theme->getName();
        $originalDescription = $theme->getDescription();
        $originalImage = $theme->getImage();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/'.$id.'/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'theme[name]' => '', // invalide (NotBlank)
            'theme[description]' => 'Description modifiée (ne doit pas être persistée)',
            'theme[image]' => '/img-invalid.jpg',
        ]);

        $this->client->submit($form);

        // Pas de redirect => on reste sur la page (200)
        self::assertResponseStatusCodeSame(200);

        // Erreur de formulaire : selon le thème Symfony, l'erreur sort souvent ici.
        // On met 2 assertions tolérantes : au moins une des deux doit matcher.
        $hasError = $this->client->getCrawler()->filter('.form-error-message')->count() > 0
            || $this->client->getCrawler()->filter('.invalid-feedback')->count() > 0
            || str_contains($this->client->getResponse()->getContent(), 'Le nom est obligatoire.');

        self::assertTrue($hasError, 'Aucune erreur de validation visible pour le champ "name".');

        // DB inchangée
        $this->em->clear();
        $reloaded = $this->em->getRepository(Theme::class)->find($id);
        self::assertNotNull($reloaded);

        self::assertSame($originalName, $reloaded->getName());
        self::assertSame($originalDescription, $reloaded->getDescription());
        self::assertSame($originalImage, $reloaded->getImage());
    }
}