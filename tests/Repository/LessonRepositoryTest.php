<?php

namespace App\Tests\Repository;

use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Repository\LessonRepository;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LessonRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LessonRepository $repo;
    private ReferenceRepository $refRepo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        $db = $container->get(DatabaseToolCollection::class)->get();
        $executor = $db->loadFixtures([
            ThemeFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(LessonRepository::class);
        $this->refRepo = $executor->getReferenceRepository();

        $this->em->clear();
    }

    private function getGuitarCursusManaged(): Cursus
    {
        $cursusRef = $this->refRepo->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);
        self::assertInstanceOf(Cursus::class, $cursusRef);

        $cursus = $this->em->getRepository(Cursus::class)->find($cursusRef->getId());
        self::assertNotNull($cursus);

        return $cursus;
    }

    private function getGuitarLesson1Managed(): Lesson
    {
        $lessonRef = $this->refRepo->getReference(ThemeFixtures::LESSON_GUITAR_1_REF, Lesson::class);
        self::assertInstanceOf(Lesson::class, $lessonRef);

        $lesson = $this->em->getRepository(Lesson::class)->find($lessonRef->getId());
        self::assertNotNull($lesson);

        return $lesson;
    }

    public function testReadLessonFromFixturesByReference(): void
    {
        $lesson = $this->getGuitarLesson1Managed();

        self::assertSame('Découverte de l’instrument', $lesson->getTitle());
        self::assertNotNull($lesson->getCursus());
        self::assertSame('Cursus d’initiation à la guitare', $lesson->getCursus()->getName());
    }

    public function testCRUDCreateReadUpdateDelete(): void
    {
        $cursus = $this->getGuitarCursusManaged();

        $lesson = (new Lesson())
            ->setTitle('Leçon CRUD Test')
            ->setPrice(12.3456)
            ->setCursus($cursus)
            ->setFiche('Fiche initiale')
            ->setVideoUrl('https://example.com/initial')
            ->setImage('initial.jpg');

        $this->em->persist($lesson);
        $this->em->flush();

        self::assertNotNull($lesson->getId());
        $lessonId = $lesson->getId();

        $this->em->clear();

        $found = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($found);

        self::assertSame('Leçon CRUD Test', $found->getTitle());
        self::assertEqualsWithDelta(12.35, (float) $found->getPrice(), 0.0001);
        self::assertSame('Fiche initiale', $found->getFiche());
        self::assertSame('https://example.com/initial', $found->getVideoUrl());
        self::assertSame('initial.jpg', $found->getImage());
        self::assertSame($cursus->getId(), $found->getCursus()?->getId());

        $found->setTitle('Leçon CRUD Test (Updated)')
            ->setPrice(99.999)
            ->setFiche('Fiche modifiée')
            ->setVideoUrl('https://example.com/updated')
            ->setImage('updated.jpg');

        $this->em->flush();
        $this->em->clear();

        $updated = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($updated);

        self::assertSame('Leçon CRUD Test (Updated)', $updated->getTitle());
        self::assertEqualsWithDelta(100.00, (float) $updated->getPrice(), 0.0001);
        self::assertSame('Fiche modifiée', $updated->getFiche());
        self::assertSame('https://example.com/updated', $updated->getVideoUrl());
        self::assertSame('updated.jpg', $updated->getImage());

        $this->em->remove($updated);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->getRepository(Lesson::class)->find($lessonId));
    }

    public function testCreateAdminFilterQueryBuilderFiltersByQuery(): void
    {
        $results = $this->repo
            ->createAdminFilterQueryBuilder('instrument')
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($results);

        foreach ($results as $lesson) {
            self::assertInstanceOf(Lesson::class, $lesson);
            self::assertStringContainsStringIgnoringCase('instrument', (string) $lesson->getTitle());
        }
    }

    public function testCreateAdminFilterQueryBuilderFiltersByStatusActiveAndArchived(): void
    {
        $active = $this->repo
            ->createAdminFilterQueryBuilder(null, 'active')
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($active);
        foreach ($active as $lesson) {
            self::assertTrue($lesson->isActive());
        }

        $lesson = $this->getGuitarLesson1Managed();
        $lesson->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $archived = $this->repo
            ->createAdminFilterQueryBuilder(null, 'archived')
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($archived);
        foreach ($archived as $l) {
            self::assertFalse($l->isActive());
        }
    }

    public function testCreateAdminFilterQueryBuilderFiltersByCursusId(): void
    {
        $cursus = $this->getGuitarCursusManaged();

        $results = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', $cursus->getId())
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($results);

        foreach ($results as $lesson) {
            self::assertSame($cursus->getId(), $lesson->getCursus()?->getId());
        }
    }

    public function testCreateAdminFilterQueryBuilderFiltersByThemeId(): void
    {
        $lesson = $this->getGuitarLesson1Managed();
        $themeId = $lesson->getCursus()?->getTheme()?->getId();

        self::assertNotNull($themeId);

        $results = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', null, $themeId)
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($results);

        foreach ($results as $l) {
            self::assertSame($themeId, $l->getCursus()?->getTheme()?->getId());
        }
    }

    public function testCreateAdminFilterQueryBuilderSortTitleAscAndDesc(): void
    {
        $asc = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', null, null, 'title_asc')
            ->getQuery()
            ->getResult();

        self::assertGreaterThanOrEqual(2, count($asc));

        for ($i = 1; $i < count($asc); $i++) {
            $prev = (string) $asc[$i - 1]->getTitle();
            $curr = (string) $asc[$i]->getTitle();

            self::assertTrue(
                strcasecmp($prev, $curr) <= 0,
                sprintf('"%s" should be <= "%s"', $prev, $curr)
            );
        }

        $desc = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', null, null, 'title_desc')
            ->getQuery()
            ->getResult();

        self::assertGreaterThanOrEqual(2, count($desc));

        for ($i = 1; $i < count($desc); $i++) {
            $prev = (string) $desc[$i - 1]->getTitle();
            $curr = (string) $desc[$i]->getTitle();

            self::assertTrue(
                strcasecmp($prev, $curr) >= 0,
                sprintf('"%s" should be >= "%s"', $prev, $curr)
            );
        }
    }

    public function testCreateAdminFilterQueryBuilderSortPriceAscAndDesc(): void
    {
        $asc = $this->repo
            ->createAdminFilterQueryBuilder(null, 'all', null, null, 'price_asc')
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
            ->createAdminFilterQueryBuilder(null, 'all', null, null, 'price_desc')
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

    public function testFindVisibleByCursusReturnsOnlyVisibleLessons(): void
    {
        $cursus = $this->getGuitarCursusManaged();

        $lessons = $this->repo->findVisibleByCursus($cursus);
        self::assertNotEmpty($lessons);

        foreach ($lessons as $lesson) {
            self::assertInstanceOf(Lesson::class, $lesson);
            self::assertTrue($lesson->isActive());
            self::assertNotNull($lesson->getCursus());
            self::assertTrue($lesson->getCursus()->isActive());
            self::assertNotNull($lesson->getCursus()->getTheme());
            self::assertTrue($lesson->getCursus()->getTheme()->isActive());
            self::assertSame($cursus->getId(), $lesson->getCursus()->getId());
        }
    }

    public function testFindVisibleLessonReturnsLessonWhenAllActive(): void
    {
        $lesson = $this->getGuitarLesson1Managed();

        $found = $this->repo->findVisibleLesson($lesson->getId());

        self::assertNotNull($found);
        self::assertSame($lesson->getId(), $found->getId());
    }

    public function testFindVisibleLessonReturnsNullWhenLessonIsInactive(): void
    {
        $lesson = $this->getGuitarLesson1Managed();
        $lesson->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $found = $this->repo->findVisibleLesson($lesson->getId());

        self::assertNull($found);
    }

    public function testFindVisibleLessonReturnsNullWhenCursusIsInactive(): void
    {
        $lesson = $this->getGuitarLesson1Managed();
        $cursus = $lesson->getCursus();

        self::assertNotNull($cursus);

        $cursus->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $found = $this->repo->findVisibleLesson($lesson->getId());

        self::assertNull($found);
    }

    public function testFindVisibleLessonReturnsNullWhenThemeIsInactive(): void
    {
        $lesson = $this->getGuitarLesson1Managed();
        $theme = $lesson->getCursus()?->getTheme();

        self::assertNotNull($theme);

        $theme->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $found = $this->repo->findVisibleLesson($lesson->getId());

        self::assertNull($found);
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