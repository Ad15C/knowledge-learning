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

        foreach ($lessons as $i => $lesson) {
            $lesson->setIsActive($i !== 0);
        }

        if (isset($lessons[0])) {
            $lessons[0]->setTitle('AAA - Unique Lesson Search Token');
            $lessons[0]->setPrice('99.00');
        }

        if (isset($lessons[1])) {
            $lessons[1]->setTitle('MMM - Lesson Title');
            $lessons[1]->setPrice('5.00');
        }

        if (isset($lessons[2])) {
            $lessons[2]->setTitle('ZZZ - Lesson Title');
            $lessons[2]->setPrice('50.00');
        }

        $this->em->flush();
        $this->em->clear();
    }

    private function getDisplayedLessonTitles(Crawler $crawler): array
    {
        return $crawler
            ->filter('.lesson-card .lesson-title > span:first-child')
            ->each(fn (Crawler $node) => trim($node->text()));
    }

    private function getAnyCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy([]);
        self::assertNotNull($cursus, 'No cursus found (fixtures missing?).');

        return $cursus;
    }

    private function getAnyTheme(): Theme
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy([]);
        self::assertNotNull($theme, 'No theme found (fixtures missing?).');

        return $theme;
    }

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
            $qb->andWhere('c.id = :cursusId')
                ->setParameter('cursusId', $cursusId);
        }

        if ($themeId) {
            $qb->andWhere('t.id = :themeId')
                ->setParameter('themeId', $themeId);
        }

        switch ($sort) {
            case 'title_asc':
                $qb->orderBy('l.title', 'ASC');
                break;

            case 'title_desc':
                $qb->orderBy('l.title', 'DESC');
                break;

            case 'price_asc':
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
                break;
        }

        /** @var Lesson[] $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(fn (Lesson $lesson) => (string) $lesson->getTitle(), $rows);
    }

    public function testIndexPageLoads(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.admin-page-title', 'Leçons');
        self::assertSelectorExists('form.admin-filters');
        self::assertSelectorExists('.lesson-grid');
        self::assertGreaterThan(0, $crawler->filter('.lesson-card')->count());
    }

    public function testFilterByQueryFiltersOnTitle(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['q' => 'Unique Lesson Search Token']);
        self::assertResponseIsSuccessful();

        $titles = $this->getDisplayedLessonTitles($crawler);
        self::assertNotEmpty($titles);

        foreach ($titles as $title) {
            self::assertStringContainsString('Unique Lesson Search Token', $title);
        }
    }

    public function testFilterByStatusAllShowsBothBadgesInDataset(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['status' => 'all']);
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(
            0,
            $crawler->filter('.lesson-card .badge-active')->count(),
            'Expected at least one active lesson.'
        );

        self::assertGreaterThan(
            0,
            $crawler->filter('.lesson-card .badge-archived')->count(),
            'Expected at least one archived lesson.'
        );
    }

    public function testFilterByStatusActive(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['status' => 'active']);
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(0, $crawler->filter('.lesson-card')->count());
        self::assertSame(0, $crawler->filter('.lesson-card .badge-archived')->count());
        self::assertGreaterThan(0, $crawler->filter('.lesson-card .badge-active')->count());
    }

    public function testFilterByStatusArchived(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->requestIndex(['status' => 'archived']);
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(0, $crawler->filter('.lesson-card')->count());
        self::assertGreaterThan(0, $crawler->filter('.lesson-card .badge-archived')->count());
        self::assertSame(0, $crawler->filter('.lesson-card .badge-active')->count());
    }

    public function testFilterByCursusId(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();

        $crawler = $this->requestIndex(['cursus' => $cursus->getId()]);
        self::assertResponseIsSuccessful();

        $titles = $this->getDisplayedLessonTitles($crawler);

        if (empty($titles)) {
            self::assertSelectorTextContains('.lesson-grid', 'Aucune leçon pour le moment.');
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
            self::assertSelectorTextContains('.lesson-grid', 'Aucune leçon pour le moment.');
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

        $crawler = $this->requestIndex(['theme' => 999999]);
        self::assertResponseIsSuccessful();

        self::assertSame(0, $crawler->filter('.lesson-card')->count());
        self::assertSelectorTextContains('.lesson-grid', 'Aucune leçon pour le moment.');
    }

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

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}