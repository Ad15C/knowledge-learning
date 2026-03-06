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

        $all = $this->em->getRepository(Cursus::class)->findAll();

        if (count($all) > 0) {
            foreach ($all as $i => $cursus) {
                $cursus->setIsActive($i !== 0);
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
        $qs = $query ? ('?' . http_build_query($query)) : '';

        return $this->client->request('GET', 'https://localhost/admin/cursus' . $qs);
    }

    private function getDisplayedCursusNames(Crawler $crawler): array
    {
        return $crawler
            ->filter('.cursus-card .cursus-card-title > span:first-child')
            ->each(fn (Crawler $node) => trim($node->text()));
    }

    /**
     * @return Cursus[]
     */
    private function getExpectedFromDb(?string $q, string $status, ?int $themeId, string $sort): array
    {
        /** @var Cursus[] $all */
        $all = $this->em->getRepository(Cursus::class)->findAll();

        $filtered = array_values(array_filter($all, function (Cursus $cursus) use ($q, $status, $themeId) {
            if ($q !== null && trim($q) !== '') {
                $needle = mb_strtolower(trim($q));
                $haystack = mb_strtolower($cursus->getName() ?? '');

                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }

            if ($status === 'active' && !$cursus->isActive()) {
                return false;
            }

            if ($status === 'archived' && $cursus->isActive()) {
                return false;
            }

            if ($themeId !== null) {
                $currentThemeId = $cursus->getTheme()?->getId();

                if ($currentThemeId !== $themeId) {
                    return false;
                }
            }

            return true;
        }));

        $sort = $sort ?: 'id_desc';

        usort($filtered, function (Cursus $a, Cursus $b) use ($sort) {
            return match ($sort) {
                'name_asc' => strcmp(
                    mb_strtolower($a->getName() ?? ''),
                    mb_strtolower($b->getName() ?? '')
                ),
                'name_desc' => strcmp(
                    mb_strtolower($b->getName() ?? ''),
                    mb_strtolower($a->getName() ?? '')
                ),
                'price_asc' => ($a->getPrice() ?? 0) <=> ($b->getPrice() ?? 0),
                'price_desc' => ($b->getPrice() ?? 0) <=> ($a->getPrice() ?? 0),
                'id_desc' => ($b->getId() ?? 0) <=> ($a->getId() ?? 0),
                default => ($b->getId() ?? 0) <=> ($a->getId() ?? 0),
            };
        });

        return $filtered;
    }

    public function testIndexWithoutFiltersShowsListAndThemeName(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.admin-page-title', 'Cursus');
        self::assertSelectorExists('form.admin-filters');
        self::assertSelectorExists('.cursus-grid');

        self::assertGreaterThan(
            0,
            $crawler->filter('.cursus-card')->count(),
            'Aucune carte cursus trouvée.'
        );

        self::assertSelectorExists('.cursus-card .cursus-meta');
        self::assertStringContainsString('Thème', $this->client->getResponse()->getContent());
    }

    public function testFilterByQueryGuitare(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['q' => 'guitare']);

        self::assertResponseIsSuccessful();

        $names = $this->getDisplayedCursusNames($crawler);

        self::assertNotEmpty($names, 'Aucun cursus affiché pour la recherche "guitare".');

        foreach ($names as $name) {
            self::assertStringContainsStringIgnoringCase('guitare', $name);
        }
    }

    public function testFilterByStatusActiveAndArchived(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['status' => 'active']);
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(0, $crawler->filter('.cursus-card')->count());
        self::assertSame(0, $crawler->filter('.cursus-card .badge-archived')->count());

        $crawler = $this->requestIndex(['status' => 'archived']);
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(0, $crawler->filter('.cursus-card')->count());
        self::assertGreaterThan(0, $crawler->filter('.cursus-card .badge-archived')->count());
        self::assertSame(0, $crawler->filter('.cursus-card .badge-active')->count());
    }

    public function testFilterByThemeId(): void
    {
        $this->loginAsAdmin();

        /** @var Theme|null $theme */
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        $crawler = $this->requestIndex(['theme' => $theme->getId()]);
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(0, $crawler->filter('.cursus-card')->count());

        $crawler->filter('.cursus-card .cursus-meta')->each(function (Crawler $node) {
            self::assertStringContainsString('Musique', $node->text());
        });
    }

    public function testUnknownThemeIdShowsEmptyListMessage(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['theme' => 999999]);
        self::assertResponseIsSuccessful();

        self::assertSame(0, $crawler->filter('.cursus-card')->count());
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

        $displayedNames = $this->getDisplayedCursusNames($crawler);

        $expected = $this->getExpectedFromDb(null, 'all', null, $sort);
        $expectedNames = array_map(
            fn (Cursus $cursus) => (string) $cursus->getName(),
            $expected
        );

        self::assertSame($expectedNames, $displayedNames);
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

        $displayedNames = $this->getDisplayedCursusNames($crawler);

        $expected = $this->getExpectedFromDb(null, 'all', null, 'id_desc');
        $expectedNames = array_map(
            fn (Cursus $cursus) => (string) $cursus->getName(),
            $expected
        );

        self::assertSame($expectedNames, $displayedNames);
    }
}