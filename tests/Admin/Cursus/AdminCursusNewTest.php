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

class AdminCursusNewTest extends WebTestCase
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

    // -------------------------
    // SECURITY
    // -------------------------

    public function testNewGetRedirectsToLoginWhenNotLoggedIn(): void
    {
        $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseRedirects('/login');
    }

    public function testNewPostRedirectsToLoginWhenNotLoggedIn(): void
    {
        $this->client->request('POST', 'https://localhost/admin/cursus/new', [
            'cursus' => [
                'name' => 'Hack',
                'price' => '10.00',
            ],
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testNewGetIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testNewPostIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $this->client->request('POST', 'https://localhost/admin/cursus/new', [
            'cursus' => [
                'name' => 'Hack',
                'price' => '10.00',
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // -------------------------
    // HELPERS
    // -------------------------

    private function setAllThemesActive(bool $active): void
    {
        foreach ($this->em->getRepository(Theme::class)->findAll() as $theme) {
            $theme->setIsActive($active);
        }

        $this->em->flush();
    }

    private function getThemeByName(string $name): Theme
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => $name]);
        self::assertNotNull($theme, sprintf('Theme "%s" not found.', $name));

        return $theme;
    }

    private function getThemeSelectOptionLabels(Crawler $crawler): array
    {
        $labels = [];

        $crawler->filter('select[name="cursus[theme]"] option')->each(function (Crawler $opt) use (&$labels) {
            $labels[] = trim($opt->text());
        });

        return $labels;
    }

    // -------------------------
    // FUNCTIONAL
    // -------------------------

    public function testNewGetWithActiveThemesShowsFormAndEnabledSubmit(): void
    {
        $this->loginAsAdmin();

        $this->setAllThemesActive(true);

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1', 'Créer un cursus');
        self::assertSelectorNotExists('.admin-alert.admin-alert-danger');

        $btn = $crawler->selectButton('Créer');
        self::assertGreaterThan(0, $btn->count(), 'Submit button "Créer" not found');
        self::assertNull($btn->attr('disabled'), 'Submit button should not be disabled when active themes exist');

        self::assertSelectorExists('input[name="cursus[name]"]');
        self::assertSelectorExists('select[name="cursus[theme]"]');
        self::assertSelectorExists('input[name="cursus[price]"]');
        self::assertSelectorExists('textarea[name="cursus[description]"]');
        self::assertSelectorExists('input[name="cursus[image]"]');
    }

    public function testThemeSelectContainsOnlyActiveThemes(): void
    {
        $this->loginAsAdmin();

        $this->setAllThemesActive(true);
        $informatique = $this->getThemeByName('Informatique');
        $informatique->setIsActive(false);
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();

        $labels = $this->getThemeSelectOptionLabels($crawler);

        self::assertContains('— Choisir un thème —', $labels);
        self::assertContains('Musique', $labels);
        self::assertNotContains('Informatique', $labels);
    }

    public function testNewPostWithActiveThemesCreatesCursusAndIsActiveByDefault(): void
    {
        $this->loginAsAdmin();

        $this->setAllThemesActive(true);
        $musique = $this->getThemeByName('Musique');

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();
        $form['cursus[name]'] = 'Cursus Test New';
        $form['cursus[theme]'] = (string) $musique->getId();
        $form['cursus[price]'] = '88.50';
        $form['cursus[description]'] = 'Description test';
        $form['cursus[image]'] = '';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/cursus');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Cursus créé.');

        /** @var Cursus|null $created */
        $created = $this->em->getRepository(Cursus::class)->findOneBy(['name' => 'Cursus Test New']);
        self::assertNotNull($created);
        self::assertTrue($created->isActive());
        self::assertSame('Musique', $created->getTheme()?->getName());
        self::assertEquals(88.50, (float) $created->getPrice());
    }

    public function testNewGetWithNoActiveThemesShowsImpossibleMessageAndDisabledSubmit(): void
    {
        $this->loginAsAdmin();

        $this->setAllThemesActive(false);

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.admin-alert.admin-alert-danger');
        self::assertSelectorTextContains('.admin-alert.admin-alert-danger', 'Aucun thème actif disponible');

        $btn = $crawler->selectButton('Créer');
        self::assertGreaterThan(0, $btn->count());
        self::assertNotNull($btn->attr('disabled'));
    }

    public function testNewPostManualWhenNoActiveThemesRedirectsToThemeIndexWithFlashError(): void
    {
        $this->loginAsAdmin();

        $this->setAllThemesActive(false);

        $this->client->request('POST', 'https://localhost/admin/cursus/new', [
            'cursus' => [
                'name' => 'Should Not Create',
                'price' => '10.00',
            ],
        ]);

        self::assertResponseRedirects('/admin/themes');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-error');
        self::assertSelectorTextContains('.flash-messages .flash.flash-error', 'Aucun thème actif disponible');

        $notCreated = $this->em->getRepository(Cursus::class)->findOneBy(['name' => 'Should Not Create']);
        self::assertNull($notCreated);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}