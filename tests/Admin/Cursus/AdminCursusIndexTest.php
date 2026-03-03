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

class AdminCursusIndexTest extends WebTestCase
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

        // On force un état mixte actif/archivé pour tester status
        $all = $this->em->getRepository(Cursus::class)->findAll();
        if (count($all) > 0) {
            // Archive le premier, active les autres (stable)
            foreach ($all as $i => $c) {
                $c->setIsActive($i !== 0);
            }
            $this->em->flush();
        }
    }

    private function loginAsAdmin(): void
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin);
        $this->client->loginUser($admin);
    }

    private function requestIndex(array $query = []): Crawler
    {
        $qs = $query ? ('?'.http_build_query($query)) : '';
        return $this->client->request('GET', 'https://localhost/admin/cursus'.$qs);
    }

    /**
     * Extrait les noms affichés dans l'ordre (h2 > span[0]).
     */
    private function getDisplayedCursusNames(Crawler $crawler): array
    {
        $names = [];
        $crawler->filter('.cursus-card h2 > span:first-child')->each(function (Crawler $node) use (&$names) {
            $names[] = trim($node->text());
        });
        return $names;
    }

    /**
     * Calcule l'ordre attendu en PHP (sans réutiliser le repo) selon tes règles.
     */
    private function getExpectedFromDb(?string $q, string $status, ?int $themeId, string $sort): array
    {
        /** @var Cursus[] $all */
        $all = $this->em->getRepository(Cursus::class)->findAll();

        // filtres
        $filtered = array_values(array_filter($all, function (Cursus $c) use ($q, $status, $themeId) {
            if ($q !== null && $q !== '') {
                $needle = mb_strtolower(trim($q));
                $hay = mb_strtolower($c->getName() ?? '');
                if (!str_contains($hay, $needle)) {
                    return false;
                }
            }

            if ($status === 'active' && !$c->isActive()) {
                return false;
            }
            if ($status === 'archived' && $c->isActive()) {
                return false;
            }

            if ($themeId) {
                $cid = $c->getTheme()?->getId();
                if ($cid !== $themeId) {
                    return false;
                }
            }

            return true;
        }));

        // tri
        $sort = $sort ?? 'id_desc';
        usort($filtered, function (Cursus $a, Cursus $b) use ($sort) {
            switch ($sort) {
                case 'name_asc':
                    return strcmp(mb_strtolower($a->getName() ?? ''), mb_strtolower($b->getName() ?? ''));
                case 'name_desc':
                    return strcmp(mb_strtolower($b->getName() ?? ''), mb_strtolower($a->getName() ?? ''));
                case 'price_asc':
                    return ($a->getPrice() ?? 0) <=> ($b->getPrice() ?? 0);
                case 'price_desc':
                    return ($b->getPrice() ?? 0) <=> ($a->getPrice() ?? 0);
                case 'id_desc':
                default:
                    // fallback id_desc si invalide
                    return ($b->getId() ?? 0) <=> ($a->getId() ?? 0);
            }
        });

        return $filtered;
    }

    public function testIndexWithoutFiltersShowsListAndThemeName(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex();
        self::assertResponseIsSuccessful();

        // header page
        self::assertSelectorTextContains('h1', 'Cursus');

        // il doit y avoir des cartes
        self::assertGreaterThan(0, $crawler->filter('.cursus-card')->count(), 'No cursus cards found');

        // vérifie affichage "Thème : XXX" sur au moins une carte
        self::assertSelectorExists('.cursus-card .cursus-meta');
        self::assertStringContainsString('Thème :', $this->client->getResponse()->getContent());
    }

    public function testFilterByQueryGuitare(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['q' => 'guitare']);
        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedCursusNames($crawler);

        // Avec tes fixtures, il y a "Cursus d’initiation à la guitare"
        self::assertNotEmpty($names);
        foreach ($names as $n) {
            self::assertStringContainsStringIgnoringCase('guitare', $n);
        }
    }

    public function testFilterByStatusActiveAndArchived(): void
    {
        $this->loginAsAdmin();

        // Active
        $crawler = $this->requestIndex(['status' => 'active']);
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('badge-archived', $html);

        // Archived
        $crawler = $this->requestIndex(['status' => 'archived']);
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        // au moins un inactif vu qu'on en a forcé un dans setUp()
        self::assertStringContainsString('badge-archived', $html);
    }

    public function testFilterByThemeId(): void
    {
        $this->loginAsAdmin();

        /** @var Theme|null $musique */
        $musique = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($musique);

        $crawler = $this->requestIndex(['theme' => $musique->getId()]);
        self::assertResponseIsSuccessful();

        // Sur la page, toutes les cartes doivent afficher "Thème : Musique"
        $crawler->filter('.cursus-card .cursus-meta')->each(function (Crawler $node) {
            self::assertStringContainsString('Musique', $node->text());
        });
    }

    public function testThemeIdInexistantShowsEmptyListMessage(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['theme' => 999999]);
        self::assertResponseIsSuccessful();

        // Comme le repo filtre sur t.id = :themeId => liste vide
        self::assertSelectorTextContains('.cursus-grid', 'Aucun cursus pour le moment.');
    }

    /**
     * @dataProvider sortProvider
     */
    public function testSortWorks(string $sort): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['sort' => $sort]);
        self::assertResponseIsSuccessful();

        $displayed = $this->getDisplayedCursusNames($crawler);

        $expected = $this->getExpectedFromDb(null, 'all', null, $sort);
        $expectedNames = array_map(fn (Cursus $c) => (string) $c->getName(), $expected);

        self::assertSame($expectedNames, $displayed);
    }

    public static function sortProvider(): array
    {
        return [
            ['id_desc'],
            ['name_asc'],
            ['name_desc'],
            ['price_asc'],
            ['price_desc'],
        ];
    }

    public function testInvalidSortFallsBackToIdDesc(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['sort' => 'WRONG_VALUE']);
        self::assertResponseIsSuccessful();

        $displayed = $this->getDisplayedCursusNames($crawler);

        $expected = $this->getExpectedFromDb(null, 'all', null, 'id_desc');
        $expectedNames = array_map(fn (Cursus $c) => (string) $c->getName(), $expected);

        self::assertSame($expectedNames, $displayed);
    }
}