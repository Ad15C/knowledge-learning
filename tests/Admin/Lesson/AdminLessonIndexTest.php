<?php

namespace App\Tests\Admin\Lesson;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AdminLessonIndexTest extends WebTestCase
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

        $this->seedStableData();
    }

    private function loginAsAdmin(): void
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture not found.');
        $this->client->loginUser($admin);
    }

    private function requestIndex(array $query = []): Crawler
    {
        $qs = $query ? ('?' . http_build_query($query)) : '';
        return $this->client->request('GET', 'https://localhost/admin/lesson' . $qs);
    }

    private function seedStableData(): void
    {
        /** @var Lesson[] $lessons */
        $lessons = $this->em->getRepository(Lesson::class)->findAll();
        self::assertNotEmpty($lessons, 'No lessons found (fixtures missing?).');

        // Mix status: 1 archived, le reste active
        foreach ($lessons as $i => $l) {
            $l->setIsActive($i !== 0);
        }

        // Titres contrôlés (pour q + tri)
        $lessons[0]->setTitle('AAA - Unique Lesson Search Token');
        if (isset($lessons[1])) {
            $lessons[1]->setTitle('MMM - Lesson Title');
        }
        if (isset($lessons[2])) {
            $lessons[2]->setTitle('ZZZ - Lesson Title');
        }

        // Prix contrôlés (pour tri price)
        if (isset($lessons[0])) {
            $lessons[0]->setPrice('99.00');
        }
        if (isset($lessons[1])) {
            $lessons[1]->setPrice('5.00');
        }
        if (isset($lessons[2])) {
            $lessons[2]->setPrice('50.00');
        }

        $this->em->flush();
        $this->em->clear();
    }

    private function getDisplayedLessonTitles(Crawler $crawler): array
    {
        $titles = [];
        $crawler->filter('.lesson-card h2.lesson-title > span:first-child')->each(function (Crawler $node) use (&$titles) {
            $titles[] = trim($node->text());
        });
        return $titles;
    }

    private function getAnyCursus(): Cursus
    {
        $c = $this->em->getRepository(Cursus::class)->findOneBy([]);
        self::assertNotNull($c, 'No cursus found (fixtures missing?).');
        return $c;
    }

    private function getAnyTheme(): Theme
    {
        $t = $this->em->getRepository(Theme::class)->findOneBy([]);
        self::assertNotNull($t, 'No theme found (fixtures missing?).');
        return $t;
    }

    /**
     * Calcule l'ordre attendu EN BASE, avec les mêmes règles que LessonRepository,
     * sans réutiliser le repo (on construit notre propre QB).
     */
    private function getExpectedTitlesFromDb(?string $q, string $status, ?int $cursusId, ?int $themeId, string $sort): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->distinct()
            ->leftJoin('l.cursus', 'c')
            ->leftJoin('c.theme', 't');

        if ($q !== null && $q !== '') {
            $qb->andWhere('LOWER(l.title) LIKE :q')
               ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
        }

        if ($status === 'active') {
            $qb->andWhere('l.isActive = true');
        } elseif ($status === 'archived') {
            $qb->andWhere('l.isActive = false');
        }

        if ($cursusId) {
            $qb->andWhere('c.id = :cursusId')->setParameter('cursusId', $cursusId);
        }

        if ($themeId) {
            $qb->andWhere('t.id = :themeId')->setParameter('themeId', $themeId);
        }

        switch ($sort) {
            case 'title_asc':
                $qb->orderBy('l.title', 'ASC');
                break;
            case 'title_desc':
                $qb->orderBy('l.title', 'DESC');
                break;
            case 'price_asc':
                // NULL last (si jamais nullable dans un autre env)
                $qb->addOrderBy('CASE WHEN l.price IS NULL THEN 1 ELSE 0 END', 'ASC')
                   ->addOrderBy('l.price', 'ASC');
                break;
            case 'price_desc':
                $qb->addOrderBy('CASE WHEN l.price IS NULL THEN 1 ELSE 0 END', 'ASC')
                   ->addOrderBy('l.price', 'DESC');
                break;
            case 'id_desc':
            default:
                $qb->orderBy('l.id', 'DESC');
        }

        /** @var Lesson[] $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(fn (Lesson $l) => (string) $l->getTitle(), $rows);
    }

    // -------------------------------------------------
    // BASIC
    // -------------------------------------------------

    public function testIndexPageLoads(): void
    {
        $this->loginAsAdmin();

        $this->requestIndex();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Leçons');
    }

    // -------------------------------------------------
    // FILTERS
    // -------------------------------------------------

    public function testFilterByQueryFiltersOnTitle(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['q' => 'Unique Lesson Search Token']);
        self::assertResponseIsSuccessful();

        $titles = $this->getDisplayedLessonTitles($crawler);
        self::assertNotEmpty($titles);

        foreach ($titles as $t) {
            self::assertStringContainsString('Unique Lesson Search Token', $t);
        }
    }

    public function testFilterByStatusAllShowsBothBadgesInDataset(): void
    {
        $this->loginAsAdmin();

        $this->requestIndex(['status' => 'all']);
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('badge-archived', $html, 'Expected at least one archived lesson.');
        self::assertStringContainsString('badge-active', $html, 'Expected at least one active lesson.');
    }

    public function testFilterByStatusActive(): void
    {
        $this->loginAsAdmin();

        $this->requestIndex(['status' => 'active']);
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('badge-archived', $html);
    }

    public function testFilterByStatusArchived(): void
    {
        $this->loginAsAdmin();

        $this->requestIndex(['status' => 'archived']);
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('badge-archived', $html);
    }

    public function testFilterByCursusId(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();

        $crawler = $this->requestIndex(['cursus' => $cursus->getId()]);
        self::assertResponseIsSuccessful();

        $titles = $this->getDisplayedLessonTitles($crawler);
        if (empty($titles)) {
            self::assertStringContainsString('Aucune leçon pour le moment', (string) $this->client->getResponse()->getContent());
            return;
        }

        foreach ($titles as $title) {
            /** @var Lesson|null $lesson */
            $lesson = $this->em->getRepository(Lesson::class)->findOneBy(['title' => $title]);
            self::assertNotNull($lesson);
            self::assertSame($cursus->getId(), $lesson->getCursus()?->getId());
        }
    }

    public function testFilterByThemeIdViaJoinCursusTheme(): void
    {
        $this->loginAsAdmin();

        $theme = $this->getAnyTheme();

        $crawler = $this->requestIndex(['theme' => $theme->getId()]);
        self::assertResponseIsSuccessful();

        $titles = $this->getDisplayedLessonTitles($crawler);

        if (empty($titles)) {
            self::assertStringContainsString('Aucune leçon pour le moment', (string) $this->client->getResponse()->getContent());
            return;
        }

        foreach ($titles as $title) {
            /** @var Lesson|null $lesson */
            $lesson = $this->em->getRepository(Lesson::class)->findOneBy(['title' => $title]);
            self::assertNotNull($lesson);
            self::assertSame($theme->getId(), $lesson->getCursus()?->getTheme()?->getId());
        }
    }

    public function testFilterThemeWithNoMatchingCursusShowsEmptyList(): void
    {
        $this->loginAsAdmin();

        $this->requestIndex(['theme' => 999999]);
        self::assertResponseIsSuccessful();

        self::assertStringContainsString(
            'Aucune leçon pour le moment',
            (string) $this->client->getResponse()->getContent()
        );
    }

    // -------------------------------------------------
    // SORT
    // -------------------------------------------------

    /**
     * @dataProvider sortProvider
     */
    public function testSortWorks(string $sort): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['sort' => $sort]);
        self::assertResponseIsSuccessful();

        $displayed = $this->getDisplayedLessonTitles($crawler);

        $expected = $this->getExpectedTitlesFromDb(null, 'all', null, null, $sort);

        self::assertSame($expected, $displayed);
    }

    public static function sortProvider(): array
    {
        return [
            ['id_desc'],
            ['title_asc'],
            ['title_desc'],
            ['price_asc'],
            ['price_desc'],
        ];
    }

    public function testInvalidSortFallsBackToIdDesc(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['sort' => 'WRONG_VALUE']);
        self::assertResponseIsSuccessful();

        $displayed = $this->getDisplayedLessonTitles($crawler);
        $expected = $this->getExpectedTitlesFromDb(null, 'all', null, null, 'id_desc');

        self::assertSame($expected, $displayed);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}