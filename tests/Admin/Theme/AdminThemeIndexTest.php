<?php

namespace App\Tests\Controller\Admin\Theme;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        $admin = $this->em->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture not found. Fixtures not loaded?');
        $this->client->loginUser($admin);
    }

    /**
     * Extraction simple des noms affichés dans les cards:
     * <h2><span>Nom</span> ...
     */
    private function extractThemeNames(string $html): array
    {
        $matches = [];
        preg_match_all('/<h2>\s*<span>(.*?)<\/span>/s', $html, $matches);

        return array_values(array_filter(array_map('trim', $matches[1] ?? [])));
    }

    public function testIndexWithoutFiltersListsFixtureThemes(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/themes');
        self::assertResponseIsSuccessful();

        $names = $this->extractThemeNames((string) $this->client->getResponse()->getContent());

        self::assertContains('Musique', $names);
        self::assertContains('Informatique', $names);
        self::assertContains('Jardinage', $names);
        self::assertContains('Cuisine', $names);
    }

    public function testQFiltersByNameCaseInsensitive(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/themes?q=mus');
        self::assertResponseIsSuccessful();

        $names = $this->extractThemeNames((string) $this->client->getResponse()->getContent());

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

        $this->client->request('GET', 'https://localhost/admin/themes?status=archived');
        self::assertResponseIsSuccessful();

        $names = $this->extractThemeNames((string) $this->client->getResponse()->getContent());

        self::assertContains('Cuisine', $names);
        self::assertNotContains('Musique', $names);
        self::assertNotContains('Informatique', $names);
        self::assertNotContains('Jardinage', $names);
    }

    public function testStatusInvalidIsStableAndBehavesLikeAll(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/themes?status=foo');
        self::assertResponseIsSuccessful();

        $names = $this->extractThemeNames((string) $this->client->getResponse()->getContent());

        self::assertContains('Musique', $names);
        self::assertContains('Informatique', $names);
        self::assertContains('Jardinage', $names);
        self::assertContains('Cuisine', $names);
    }

    public function testSortNameAscAndDesc(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/themes?sort=name_asc');
        self::assertResponseIsSuccessful();
        $asc = $this->extractThemeNames((string) $this->client->getResponse()->getContent());

        $this->client->request('GET', 'https://localhost/admin/themes?sort=name_desc');
        self::assertResponseIsSuccessful();
        $desc = $this->extractThemeNames((string) $this->client->getResponse()->getContent());

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
        $old->setCreatedAt(new \DateTime('2020-01-01 00:00:00'));

        $new = (new Theme())->setName('AA New Theme')->setIsActive(true);
        $new->setCreatedAt(new \DateTime('2030-01-01 00:00:00'));

        $this->em->persist($old);
        $this->em->persist($new);
        $this->em->flush();

        $this->client->request('GET', 'https://localhost/admin/themes?sort=not_a_sort&q=Theme');
        self::assertResponseIsSuccessful();

        $names = $this->extractThemeNames((string) $this->client->getResponse()->getContent());

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
        $a->setCreatedAt(new \DateTime('2020-01-01 00:00:00'));

        $b = (new Theme())->setName('Created B')->setIsActive(true);
        $b->setCreatedAt(new \DateTime('2030-01-01 00:00:00'));

        $this->em->persist($a);
        $this->em->persist($b);
        $this->em->flush();

        $this->client->request('GET', 'https://localhost/admin/themes?sort=created_asc&q=Created');
        self::assertResponseIsSuccessful();

        $names = $this->extractThemeNames((string) $this->client->getResponse()->getContent());

        $posA = array_search('Created A', $names, true);
        $posB = array_search('Created B', $names, true);

        self::assertIsInt($posA);
        self::assertIsInt($posB);
        self::assertLessThan($posB, $posA);
    }

    public function testStatusActiveRequiresAtLeastOneActiveCursus_CurrentBehavior(): void
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

        $this->client->request('GET', 'https://localhost/admin/themes?status=active');
        self::assertResponseIsSuccessful();

        $names = $this->extractThemeNames((string) $this->client->getResponse()->getContent());

        self::assertContains('Actif Cursus Actif', $names);
        self::assertNotContains('Actif Sans Cursus', $names);
        self::assertNotContains('Actif Cursus Inactif', $names);
    }

    private function assertSelectedOption(string $html, string $selectName, string $expectedValue): void
    {
        // Cherche: <select name="status"> ... <option value="archived" selected> ...
        $pattern = sprintf(
            '/<select[^>]*name="%s"[^>]*>.*?<option[^>]*value="%s"[^>]*(selected)?[^>]*>/s',
            preg_quote($selectName, '/'),
            preg_quote($expectedValue, '/')
        );

        self::assertMatchesRegularExpression($pattern, $html, sprintf(
            'Expected option "%s" to be selected for select "%s".',
            $expectedValue,
            $selectName
        ));
    }

    public function testStatusAllExplicitShowsAllThemes(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/themes?status=all');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        $names = $this->extractThemeNames($html);

        self::assertContains('Musique', $names);
        self::assertContains('Informatique', $names);
        self::assertContains('Jardinage', $names);
        self::assertContains('Cuisine', $names);

        // Bonus: le select status reste sur "all"
        $this->assertSelectedOption($html, 'status', 'all');
    }

    public function testQAndStatusActiveCombined(): void
    {
        $this->loginAsAdmin();

        // Musique (fixture) a des cursus actifs -> doit sortir en status=active
        $this->client->request('GET', 'https://localhost/admin/themes?q=mus&status=active');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        $names = $this->extractThemeNames($html);

        self::assertContains('Musique', $names);
        self::assertNotContains('Cuisine', $names);
        self::assertNotContains('Informatique', $names);
        self::assertNotContains('Jardinage', $names);

        // Bonus: le select status reste sur "active"
        $this->assertSelectedOption($html, 'status', 'active');
    }

    public function testFiltersPersistInSelects(): void
    {
        $this->loginAsAdmin();

        // On force un thème inactif pour avoir quelque chose en archived
        $themeCuisine = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Cuisine']);
        self::assertNotNull($themeCuisine);
        $themeCuisine->setIsActive(false);
        $this->em->flush();

        $this->client->request('GET', 'https://localhost/admin/themes?status=archived&sort=name_desc&q=cui');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();

        // Le champ q doit être rempli
        self::assertStringContainsString('name="q" value="cui"', $html);

        // status sélectionné = archived
        $this->assertSelectedOption($html, 'status', 'archived');

        // sort sélectionné = name_desc
        $this->assertSelectedOption($html, 'sort', 'name_desc');
    }
}