<?php

namespace App\Tests\Controller\Admin\Theme;

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

class AdminThemeIndexTest extends WebTestCase
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

    private function requestIndex(array $query = []): Crawler
    {
        $qs = $query ? ('?' . http_build_query($query)) : '';

        return $this->client->request('GET', 'https://localhost/admin/themes' . $qs);
    }

    private function getDisplayedThemeNames(Crawler $crawler): array
    {
        return $crawler
            ->filter('.theme-card .theme-card-title > span:first-child')
            ->each(fn (Crawler $node) => trim($node->text()));
    }

    private function assertSelectedOption(Crawler $crawler, string $selectName, string $expectedValue): void
    {
        $selected = $crawler->filter(sprintf('select[name="%s"] option[selected]', $selectName));
        self::assertGreaterThan(0, $selected->count(), sprintf('No selected option found for select "%s".', $selectName));
        self::assertSame($expectedValue, (string) $selected->attr('value'));
    }

    public function testIndexWithoutFiltersListsFixtureThemes(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Thèmes');
        self::assertSelectorExists('form.admin-filters');
        self::assertSelectorExists('.themes-grid');

        $names = $this->getDisplayedThemeNames($crawler);

        self::assertContains('Musique', $names);
        self::assertContains('Informatique', $names);
        self::assertContains('Jardinage', $names);
        self::assertContains('Cuisine', $names);
    }

    public function testQFiltersByNameCaseInsensitive(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['q' => 'mus']);
        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedThemeNames($crawler);

        self::assertContains('Musique', $names);
        self::assertNotContains('Cuisine', $names);
        self::assertNotContains('Informatique', $names);
        self::assertNotContains('Jardinage', $names);
    }

    public function testStatusArchivedShowsOnlyInactiveThemes(): void
    {
        $this->loginAsAdmin();

        $themeCuisine = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Cuisine']);
        self::assertNotNull($themeCuisine);

        $themeCuisine->setIsActive(false);
        $this->em->flush();

        $crawler = $this->requestIndex(['status' => 'archived']);
        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedThemeNames($crawler);

        self::assertContains('Cuisine', $names);
        self::assertNotContains('Musique', $names);
        self::assertNotContains('Informatique', $names);
        self::assertNotContains('Jardinage', $names);
    }

    public function testStatusInvalidIsStableAndBehavesLikeAll(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['status' => 'foo']);
        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedThemeNames($crawler);

        self::assertContains('Musique', $names);
        self::assertContains('Informatique', $names);
        self::assertContains('Jardinage', $names);
        self::assertContains('Cuisine', $names);
    }

    public function testSortNameAscAndDesc(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['sort' => 'name_asc']);
        self::assertResponseIsSuccessful();
        $asc = $this->getDisplayedThemeNames($crawler);

        $crawler = $this->requestIndex(['sort' => 'name_desc']);
        self::assertResponseIsSuccessful();
        $desc = $this->getDisplayedThemeNames($crawler);

        $posCuisineAsc = array_search('Cuisine', $asc, true);
        $posMusiqueAsc = array_search('Musique', $asc, true);

        $posCuisineDesc = array_search('Cuisine', $desc, true);
        $posMusiqueDesc = array_search('Musique', $desc, true);

        self::assertIsInt($posCuisineAsc);
        self::assertIsInt($posMusiqueAsc);
        self::assertIsInt($posCuisineDesc);
        self::assertIsInt($posMusiqueDesc);

        self::assertLessThan($posMusiqueAsc, $posCuisineAsc);
        self::assertLessThan($posCuisineDesc, $posMusiqueDesc);
    }

    public function testSortInvalidFallsBackToCreatedDesc(): void
    {
        $this->loginAsAdmin();

        $old = (new Theme())->setName('ZZ Old Theme')->setIsActive(true);
        $old->setCreatedAt(new \DateTimeImmutable('2020-01-01 00:00:00'));

        $new = (new Theme())->setName('AA New Theme')->setIsActive(true);
        $new->setCreatedAt(new \DateTimeImmutable('2030-01-01 00:00:00'));

        $this->em->persist($old);
        $this->em->persist($new);
        $this->em->flush();

        $crawler = $this->requestIndex([
            'sort' => 'not_a_sort',
            'q' => 'Theme',
        ]);
        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedThemeNames($crawler);

        $posNew = array_search('AA New Theme', $names, true);
        $posOld = array_search('ZZ Old Theme', $names, true);

        self::assertIsInt($posNew);
        self::assertIsInt($posOld);
        self::assertLessThan($posOld, $posNew);
    }

    public function testCreatedAscWorksViaQueryString(): void
    {
        $this->loginAsAdmin();

        $a = (new Theme())->setName('Created A')->setIsActive(true);
        $a->setCreatedAt(new \DateTimeImmutable('2020-01-01 00:00:00'));

        $b = (new Theme())->setName('Created B')->setIsActive(true);
        $b->setCreatedAt(new \DateTimeImmutable('2030-01-01 00:00:00'));

        $this->em->persist($a);
        $this->em->persist($b);
        $this->em->flush();

        $crawler = $this->requestIndex([
            'sort' => 'created_asc',
            'q' => 'Created',
        ]);
        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedThemeNames($crawler);

        $posA = array_search('Created A', $names, true);
        $posB = array_search('Created B', $names, true);

        self::assertIsInt($posA);
        self::assertIsInt($posB);
        self::assertLessThan($posB, $posA);
    }

    public function testStatusActiveShowsOnlyThemesReturnedByCurrentQueryBehavior(): void
    {
        $this->loginAsAdmin();

        $tNoCursus = (new Theme())->setName('Actif Sans Cursus')->setIsActive(true);
        $this->em->persist($tNoCursus);

        $tOnlyInactive = (new Theme())->setName('Actif Cursus Inactif')->setIsActive(true);
        $this->em->persist($tOnlyInactive);

        $cOff = (new Cursus())
            ->setName('Cursus Off')
            ->setPrice(10)
            ->setTheme($tOnlyInactive)
            ->setIsActive(false);
        $this->em->persist($cOff);

        $tWithActive = (new Theme())->setName('Actif Cursus Actif')->setIsActive(true);
        $this->em->persist($tWithActive);

        $cOn = (new Cursus())
            ->setName('Cursus On')
            ->setPrice(10)
            ->setTheme($tWithActive)
            ->setIsActive(true);
        $this->em->persist($cOn);

        $this->em->flush();

        $crawler = $this->requestIndex(['status' => 'active']);
        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedThemeNames($crawler);

        self::assertContains('Actif Cursus Actif', $names);

        // comportement actuellement observé dans l'application
        self::assertNotContains('Actif Sans Cursus', $names);
        self::assertNotContains('Actif Cursus Inactif', $names);
    }

    public function testStatusAllExplicitShowsAllThemes(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['status' => 'all']);
        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedThemeNames($crawler);

        self::assertContains('Musique', $names);
        self::assertContains('Informatique', $names);
        self::assertContains('Jardinage', $names);
        self::assertContains('Cuisine', $names);

        $this->assertSelectedOption($crawler, 'status', 'all');
    }

    public function testQAndStatusActiveCombined(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex([
            'q' => 'mus',
            'status' => 'active',
        ]);
        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedThemeNames($crawler);

        self::assertContains('Musique', $names);
        self::assertNotContains('Cuisine', $names);
        self::assertNotContains('Informatique', $names);
        self::assertNotContains('Jardinage', $names);

        $this->assertSelectedOption($crawler, 'status', 'active');
    }

    public function testFiltersPersistInSelects(): void
    {
        $this->loginAsAdmin();

        $themeCuisine = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Cuisine']);
        self::assertNotNull($themeCuisine);
        $themeCuisine->setIsActive(false);
        $this->em->flush();

        $crawler = $this->requestIndex([
            'status' => 'archived',
            'sort' => 'name_desc',
            'q' => 'cui',
        ]);
        self::assertResponseIsSuccessful();

        $response = $this->client->getResponse()->getContent();
        self::assertIsString($response);
        self::assertStringContainsString('name="q"', $response);
        self::assertStringContainsString('value="cui"', $response);

        $this->assertSelectedOption($crawler, 'status', 'archived');
        $this->assertSelectedOption($crawler, 'sort', 'name_desc');
    }

    public function testStatusActiveShowsInfoMessage(): void
    {
        $this->loginAsAdmin();

        $this->requestIndex(['status' => 'active']);
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.admin-info-message');
        self::assertSelectorTextContains('.admin-info-message', 'Affichage des thèmes actifs.');
    }

    public function testThemeCardsShowVisibilityBadges(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex();
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(0, $crawler->filter('.theme-card')->count());
        self::assertGreaterThan(
            0,
            $crawler->filter('.theme-card .badge.bg-success, .theme-card .badge.bg-secondary')->count()
        );
    }

    public function testNoThemesShowsEmptyMessage(): void
    {
        $this->loginAsAdmin();

        foreach ($this->em->getRepository(Theme::class)->findAll() as $theme) {
            foreach ($theme->getCursus() as $cursus) {
                $this->em->remove($cursus);
            }
            $this->em->remove($theme);
        }
        $this->em->flush();

        $crawler = $this->requestIndex();
        self::assertResponseIsSuccessful();

        self::assertSame(0, $crawler->filter('.theme-card')->count());
        self::assertSelectorTextContains('.themes-grid', 'Aucun thème pour le moment.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}