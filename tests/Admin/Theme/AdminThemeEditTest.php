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

    private function getThemeByName(string $name): Theme
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => $name]);
        self::assertNotNull($theme, sprintf('Theme "%s" not found.', $name));

        return $theme;
    }

    public function testEditGetShowsPrefilledFields(): void
    {
        $this->loginAsAdmin();

        $theme = $this->getThemeByName('Musique');
        $theme->setDescription('Desc avant edit');
        $theme->setImage('/img-avant.jpg');
        $theme->setIsActive(true);
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/' . $theme->getId() . '/edit');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Modifier : ' . $theme->getName());
        self::assertSelectorExists('.admin-page-header');
        self::assertSelectorExists('a.btn.btn-secondary[href="/admin/themes"]');

        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="theme[name]"]');
        self::assertSelectorExists('textarea[name="theme[description]"]');
        self::assertSelectorExists('input[name="theme[image]"]');

        $form = $crawler->filter('form')->first()->form();
        self::assertSame('Musique', $form['theme[name]']->getValue());
        self::assertSame('Desc avant edit', $form['theme[description]']->getValue());
        self::assertSame('/img-avant.jpg', $form['theme[image]']->getValue());

        self::assertSelectorExists('button[type="submit"]');
        self::assertSelectorExists('a.btn.btn-warning');
        self::assertSelectorTextContains('a.btn.btn-warning', 'Désactiver');
    }

    public function testEditGetDoesNotShowDisableButtonWhenThemeIsInactive(): void
    {
        $this->loginAsAdmin();

        $theme = $this->getThemeByName('Musique');
        $theme->setIsActive(false);
        $this->em->flush();

        $this->client->request('GET', 'https://localhost/admin/themes/' . $theme->getId() . '/edit');
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('a.btn.btn-warning');
    }

    public function testEditPostUpdatesThemePersistsAndShowsFlash(): void
    {
        $this->loginAsAdmin();

        $theme = $this->getThemeByName('Musique');

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/' . $theme->getId() . '/edit');
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

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Thème modifié.');

        $id = $theme->getId();
        $this->em->clear();

        /** @var Theme|null $reloaded */
        $reloaded = $this->em->getRepository(Theme::class)->find($id);
        self::assertNotNull($reloaded);

        self::assertSame('Musique (modifiée)', $reloaded->getName());
        self::assertSame('Nouvelle description', $reloaded->getDescription());
        self::assertSame('/img-new.jpg', $reloaded->getImage());
    }

    public function testEditPostWithInvalidDataShowsErrorsAndDoesNotPersist(): void
    {
        $this->loginAsAdmin();

        $theme = $this->getThemeByName('Musique');

        $id = $theme->getId();
        $originalName = $theme->getName();
        $originalDescription = $theme->getDescription();
        $originalImage = $theme->getImage();

        $crawler = $this->client->request('GET', 'https://localhost/admin/themes/' . $id . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'theme[name]' => '',
            'theme[description]' => 'Description modifiée (ne doit pas être persistée)',
            'theme[image]' => '/img-invalid.jpg',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $content = (string) $this->client->getResponse()->getContent();

        $hasError =
            $this->client->getCrawler()->filter('.form-error-message')->count() > 0
            || $this->client->getCrawler()->filter('.invalid-feedback')->count() > 0
            || str_contains($content, 'Le nom est obligatoire.')
            || str_contains($content, 'This value should not be blank')
            || str_contains($content, 'Cette valeur ne doit pas être vide');

        self::assertTrue($hasError, 'Aucune erreur de validation visible pour le champ "name".');

        $this->em->clear();

        /** @var Theme|null $reloaded */
        $reloaded = $this->em->getRepository(Theme::class)->find($id);
        self::assertNotNull($reloaded);

        self::assertSame($originalName, $reloaded->getName());
        self::assertSame($originalDescription, $reloaded->getDescription());
        self::assertSame($originalImage, $reloaded->getImage());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}