<?php

namespace App\Tests\Repository;

use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use App\Repository\CursusRepository;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CursusRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CursusRepository $repo;
    private ReferenceRepository $refRepo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        $db = $container->get(DatabaseToolCollection::class)->get();
        $fixtureExecutor = $db->loadFixtures([
            ThemeFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(CursusRepository::class);
        $this->refRepo = $fixtureExecutor->getReferenceRepository();

        $this->em->clear();
    }

    private function getCursusGuitareManaged(): Cursus
    {
        $cursusRef = $this->refRepo->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);
        self::assertInstanceOf(Cursus::class, $cursusRef);

        $cursus = $this->em->getRepository(Cursus::class)->find($cursusRef->getId());
        self::assertNotNull($cursus);

        return $cursus;
    }

    private function getThemeOfCursusManaged(Cursus $cursus): Theme
    {
        $themeId = $cursus->getTheme()?->getId();
        self::assertNotNull($themeId);

        $theme = $this->em->getRepository(Theme::class)->find($themeId);
        self::assertNotNull($theme);

        return $theme;
    }

    public function testFindWithLessons(): void
    {
        $cursus = $this->getCursusGuitareManaged();

        $loaded = $this->repo->findWithLessons($cursus->getId());

        self::assertNotNull($loaded);
        self::assertSame($cursus->getId(), $loaded->getId());
        self::assertCount(2, $loaded->getLessons());
    }

    public function testFindWithLessonsReturnsNullIfNotFound(): void
    {
        self::assertNull($this->repo->findWithLessons(999999));
    }

    public function testCreateAdminFilterQueryBuilderFiltersByQuery(): void
    {
        $results = $this->repo
            ->createAdminFilterQueryBuilder('guitare', 'all', null, 'name_asc')
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($results);

        foreach ($results as $cursus) {
            self::assertInstanceOf(Cursus::class, $cursus);
            self::assertStringContainsStringIgnoringCase('guitare', (string) $cursus->getName());
        }
    }

    public function testCreateAdminFilterQueryBuilderFiltersByStatusActiveAndArchived(): void
    {
        $active = $this->repo
            ->createAdminFilterQueryBuilder(null, 'active')
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($active);
        foreach ($active as $cursus) {
            self::assertTrue($cursus->isActive());
        }

        $one = $this->getCursusGuitareManaged();
        $one->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $archived = $this->repo
            ->createAdminFilterQueryBuilder(null, 'archived')
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($archived);
        foreach ($archived as $cursus) {
            self::assertFalse($cursus->isActive());
        }
    }

    public function testCreateAdminFilterQueryBuilderFiltersByThemeId(): void
    {
        $cursus = $this->getCursusGuitareManaged();
        $themeId = $cursus->getTheme()?->getId();

        self::assertNotNull($themeId);

        $results = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', $themeId)
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($results);

        foreach ($results as $c) {
            self::assertSame($themeId, $c->getTheme()?->getId());
        }
    }

    public function testCreateAdminFilterQueryBuilderSortNameAscAndDesc(): void
    {
        $asc = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', null, 'name_asc')
            ->getQuery()
            ->getResult();

        self::assertGreaterThanOrEqual(2, count($asc));

        for ($i = 1; $i < count($asc); $i++) {
            $prev = (string) $asc[$i - 1]->getName();
            $curr = (string) $asc[$i]->getName();

            self::assertTrue(
                strcasecmp($prev, $curr) <= 0,
                sprintf('"%s" should be <= "%s"', $prev, $curr)
            );
        }

        $desc = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', null, 'name_desc')
            ->getQuery()
            ->getResult();

        self::assertGreaterThanOrEqual(2, count($desc));

        for ($i = 1; $i < count($desc); $i++) {
            $prev = (string) $desc[$i - 1]->getName();
            $curr = (string) $desc[$i]->getName();

            self::assertTrue(
                strcasecmp($prev, $curr) >= 0,
                sprintf('"%s" should be >= "%s"', $prev, $curr)
            );
        }
    }

    public function testCreateAdminFilterQueryBuilderSortPriceAscAndDesc(): void
    {
        $asc = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', null, 'price_asc')
            ->getQuery()
            ->getResult();

        self::assertGreaterThanOrEqual(2, count($asc));

        for ($i = 1; $i < count($asc); $i++) {
            $prev = (float) $asc[$i - 1]->getPrice();
            $curr = (float) $asc[$i]->getPrice();

            self::assertTrue(
                $prev <= $curr,
                sprintf('Price %s should be <= %s', $prev, $curr)
            );
        }

        $desc = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', null, 'price_desc')
            ->getQuery()
            ->getResult();

        self::assertGreaterThanOrEqual(2, count($desc));

        for ($i = 1; $i < count($desc); $i++) {
            $prev = (float) $desc[$i - 1]->getPrice();
            $curr = (float) $desc[$i]->getPrice();

            self::assertTrue(
                $prev >= $curr,
                sprintf('Price %s should be >= %s', $prev, $curr)
            );
        }
    }

    public function testFindVisibleByThemeReturnsOnlyVisibleCursus(): void
    {
        $cursus = $this->getCursusGuitareManaged();
        $theme = $this->getThemeOfCursusManaged($cursus);

        $results = $this->repo->findVisibleByTheme($theme);

        self::assertNotEmpty($results);

        foreach ($results as $c) {
            self::assertTrue($c->isActive());
            self::assertNotNull($c->getTheme());
            self::assertTrue($c->getTheme()->isActive());
            self::assertSame($theme->getId(), $c->getTheme()->getId());

            $hasAtLeastOneActiveLesson = false;
            foreach ($c->getLessons() as $lesson) {
                if ($lesson instanceof Lesson && $lesson->isActive()) {
                    $hasAtLeastOneActiveLesson = true;
                    break;
                }
            }

            self::assertTrue($hasAtLeastOneActiveLesson);
        }
    }

    public function testFindVisibleByThemeExcludesCursusIfThemeIsArchived(): void
    {
        $cursus = $this->getCursusGuitareManaged();
        $theme = $this->getThemeOfCursusManaged($cursus);

        $theme->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $themeReload = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertNotNull($themeReload);

        $results = $this->repo->findVisibleByTheme($themeReload);

        self::assertSame([], $results);
    }

    public function testFindVisibleWithVisibleLessonsReturnsCursusAndOnlyActiveLessons(): void
    {
        $cursus = $this->getCursusGuitareManaged();

        $lessons = $cursus->getLessons();
        self::assertGreaterThanOrEqual(2, $lessons->count());

        $firstLesson = $lessons->first();
        self::assertInstanceOf(Lesson::class, $firstLesson);

        $firstLesson->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->repo->findVisibleWithVisibleLessons($cursus->getId());

        self::assertNotNull($loaded);
        self::assertSame($cursus->getId(), $loaded->getId());

        foreach ($loaded->getLessons() as $lesson) {
            self::assertInstanceOf(Lesson::class, $lesson);
            self::assertTrue($lesson->isActive());
        }

        self::assertCount(1, $loaded->getLessons());
    }

    public function testFindVisibleWithVisibleLessonsReturnsNullIfCursusArchived(): void
    {
        $cursus = $this->getCursusGuitareManaged();

        $cursus->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->repo->findVisibleWithVisibleLessons($cursus->getId()));
    }

    public function testFindVisibleWithVisibleLessonsReturnsNullIfThemeArchived(): void
    {
        $cursus = $this->getCursusGuitareManaged();
        $theme = $this->getThemeOfCursusManaged($cursus);

        $theme->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->repo->findVisibleWithVisibleLessons($cursus->getId()));
    }

    public function testFindVisibleWithVisibleLessonsReturnsNullIfNoActiveLessons(): void
    {
        $cursus = $this->getCursusGuitareManaged();

        foreach ($cursus->getLessons() as $lesson) {
            if ($lesson instanceof Lesson) {
                $lesson->setIsActive(false);
            }
        }

        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->repo->findVisibleWithVisibleLessons($cursus->getId()));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em, $this->repo, $this->refRepo);

        self::ensureKernelShutdown();
    }
}