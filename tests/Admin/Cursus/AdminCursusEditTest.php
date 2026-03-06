<?php

namespace App\Tests\Admin\Cursus;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AdminCursusEditTest extends WebTestCase
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

    private function loginAsUser(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture not found.');
        $this->client->loginUser($user);
    }

    private function getAnyCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy([]);
        self::assertNotNull($cursus, 'No cursus found (fixtures missing?).');

        return $cursus;
    }

    private function getThemeByName(string $name): Theme
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => $name]);
        self::assertNotNull($theme, sprintf('Theme "%s" not found.', $name));

        return $theme;
    }

    private function getThemeSelectLabels(Crawler $crawler): array
    {
        return $crawler
            ->filter('select[name="cursus[theme]"] option')
            ->each(fn (Crawler $opt) => trim($opt->text()));
    }

    // -------------------------
    // SECURITY
    // -------------------------

    public function testEditGetRedirectsToLoginWhenNotLoggedIn(): void
    {
        $cursus = $this->getAnyCursus();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit');
        self::assertResponseRedirects('/login');
    }

    public function testEditPostRedirectsToLoginWhenNotLoggedIn(): void
    {
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit', [
            'cursus' => [
                'name' => 'Hack',
            ],
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testEditGetIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $cursus = $this->getAnyCursus();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditPostIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit', [
            'cursus' => [
                'name' => 'Hack',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // -------------------------
    // FUNCTIONAL
    // -------------------------

    public function testEditGetShowsFormAndPrefilledFields(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $cursus->setName('Nom avant edit');
        $cursus->setDescription('Desc avant edit');
        $cursus->setPrice(42.50);
        $cursus->setImage('/img-avant.jpg');
        $cursus->setIsActive(true);
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Modifier : Nom avant edit');
        self::assertSelectorExists('.admin-page-header');
        self::assertSelectorExists('.admin-form-actions');
        self::assertSelectorExists('a.btn.btn-secondary[href="/admin/cursus"]');

        $form = $crawler->filter('form')->first()->form();

        self::assertSame('Nom avant edit', $form['cursus[name]']->getValue());
        self::assertSame('Desc avant edit', $form['cursus[description]']->getValue());
        self::assertStringContainsString('42', (string) $form['cursus[price]']->getValue());
        self::assertSame('/img-avant.jpg', $form['cursus[image]']->getValue());

        self::assertSelectorExists('button[type="submit"]');
        self::assertSelectorExists('a.btn.btn-danger');
        self::assertSelectorTextContains('a.btn.btn-danger', 'Désactiver');
    }

    public function testEditGetDoesNotShowDisableButtonWhenCursusIsInactive(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $cursus->setIsActive(false);
        $this->em->flush();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit');
        self::assertResponseIsSuccessful();

        self::assertSelectorNotExists('a.btn.btn-danger');
    }

    public function testEditGetThemeSelectContainsActiveThemesAndCurrentThemeEvenIfArchived(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();

        $informatique = $this->getThemeByName('Informatique');
        $musique = $this->getThemeByName('Musique');
        $jardinage = $this->getThemeByName('Jardinage');

        $cursus->setTheme($informatique);
        $this->em->flush();

        $informatique->setIsActive(false); // thème courant inactif mais doit rester visible
        $musique->setIsActive(true);       // thème actif visible
        $jardinage->setIsActive(false);    // thème inactif non courant, ne doit pas apparaître
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit');
        self::assertResponseIsSuccessful();

        $labels = $this->getThemeSelectLabels($crawler);

        self::assertContains('— Choisir un thème —', $labels);
        self::assertContains('Musique', $labels);
        self::assertContains('Informatique', $labels);
        self::assertNotContains('Jardinage', $labels);
    }

    public function testEditPostUpdatesCursusAndRedirectsWithFlash(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $musique = $this->getThemeByName('Musique');

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $id . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'cursus[name]' => 'Cursus (modifié)',
            'cursus[theme]' => (string) $musique->getId(),
            'cursus[price]' => '99.90',
            'cursus[description]' => 'Nouvelle description',
            'cursus[image]' => '/img-new.jpg',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/cursus');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Cursus modifié.');

        $this->em->clear();

        /** @var Cursus|null $reloaded */
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertSame('Cursus (modifié)', $reloaded->getName());
        self::assertSame('Nouvelle description', $reloaded->getDescription());
        self::assertSame('/img-new.jpg', $reloaded->getImage());
        self::assertEquals(99.90, (float) $reloaded->getPrice());
        self::assertSame('Musique', $reloaded->getTheme()?->getName());
    }

    public function testEditPostWithInvalidDataShowsErrorsAndDoesNotPersist(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setName('Nom initial');
        $cursus->setDescription('Desc initiale');
        $cursus->setPrice(10.00);
        $this->em->flush();

        $this->em->clear();

        /** @var Cursus|null $before */
        $before = $this->em->getRepository(Cursus::class)->find($id);
        self::assertNotNull($before);

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $id . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'cursus[name]' => '',
            'cursus[theme]' => (string) $before->getTheme()?->getId(),
            'cursus[price]' => '10.00',
            'cursus[description]' => 'Desc initiale',
            'cursus[image]' => $before->getImage() ?? '',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $content = (string) $this->client->getResponse()->getContent();

        $hasCustom = str_contains($content, 'Le nom est obligatoire.');
        $hasDefault = str_contains($content, 'This value should not be blank')
            || str_contains($content, 'Cette valeur ne doit pas être vide');

        self::assertTrue(
            $hasCustom || $hasDefault,
            'Expected a validation message in the response content.'
        );

        $this->em->clear();

        /** @var Cursus|null $after */
        $this->em->clear();
        $after = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($after);
        self::assertSame('Nom initial', $after->getName());
        self::assertSame('Desc initiale', $after->getDescription());
        self::assertEquals(10.00, (float) $after->getPrice());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}