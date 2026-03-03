<?php

namespace App\Tests\Repository;

use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
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
        $executor = $db->loadFixtures([ThemeFixtures::class]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(LessonRepository::class);
        $this->refRepo = $executor->getReferenceRepository();

        // EM clean = tests + fiables
        $this->em->clear();
    }

    private function getGuitarCursusManaged(): Cursus
    {
        /** @var Cursus $cursusRef */
        $cursusRef = $this->refRepo->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);
        self::assertInstanceOf(Cursus::class, $cursusRef);

        $cursus = $this->em->getRepository(Cursus::class)->find($cursusRef->getId());
        self::assertNotNull($cursus);

        return $cursus;
    }

    private function getGuitarLesson1Managed(): Lesson
    {
        /** @var Lesson $lessonRef */
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

        // --- CREATE
        $lesson = (new Lesson())
            ->setTitle('Leçon CRUD Test')
            ->setPrice(12.3456) // => 12.35
            ->setCursus($cursus)
            ->setFiche('Fiche initiale')
            ->setVideoUrl('https://example.com/initial')
            ->setImage('initial.jpg');

        $this->em->persist($lesson);
        $this->em->flush();

        self::assertNotNull($lesson->getId());
        $lessonId = $lesson->getId();

        // --- READ
        $this->em->clear();

        /** @var Lesson|null $found */
        $found = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($found);

        self::assertSame('Leçon CRUD Test', $found->getTitle());
        self::assertEqualsWithDelta(12.35, (float) $found->getPrice(), 0.0001);
        self::assertSame('Fiche initiale', $found->getFiche());
        self::assertSame('https://example.com/initial', $found->getVideoUrl());
        self::assertSame('initial.jpg', $found->getImage());
        self::assertSame($cursus->getId(), $found->getCursus()?->getId());

        // --- UPDATE
        $found->setTitle('Leçon CRUD Test (Updated)')
            ->setPrice(99.999) // => 100.00
            ->setFiche('Fiche modifiée')
            ->setVideoUrl('https://example.com/updated')
            ->setImage('updated.jpg');

        $this->em->flush();
        $this->em->clear();

        /** @var Lesson|null $updated */
        $updated = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($updated);

        self::assertSame('Leçon CRUD Test (Updated)', $updated->getTitle());
        self::assertEqualsWithDelta(100.00, (float) $updated->getPrice(), 0.0001);
        self::assertSame('Fiche modifiée', $updated->getFiche());
        self::assertSame('https://example.com/updated', $updated->getVideoUrl());
        self::assertSame('updated.jpg', $updated->getImage());

        // --- DELETE
        $this->em->remove($updated);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->em->getRepository(Lesson::class)->find($lessonId));
    }

    public function testCreateAdminFilterQueryBuilderFiltersByQuery(): void
    {
        // doit matcher "Découverte de l’instrument"
        $results = $this->repo->createAdminFilterQueryBuilder('instrument')->getQuery()->getResult();

        self::assertNotEmpty($results);
        foreach ($results as $lesson) {
            self::assertInstanceOf(Lesson::class, $lesson);
            self::assertStringContainsStringIgnoringCase('instrument', (string) $lesson->getTitle());
        }
    }

    public function testCreateAdminFilterQueryBuilderFiltersByStatusActiveAndArchived(): void
    {
        // actifs (fixtures = actifs)
        $active = $this->repo->createAdminFilterQueryBuilder(null, 'active')->getQuery()->getResult();
        self::assertNotEmpty($active);
        foreach ($active as $lesson) {
            self::assertTrue($lesson->isActive());
        }

        // archive une leçon et reteste
        $lesson = $this->getGuitarLesson1Managed();
        $lesson->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $archived = $this->repo->createAdminFilterQueryBuilder(null, 'archived')->getQuery()->getResult();
        self::assertNotEmpty($archived);
        foreach ($archived as $lesson) {
            self::assertFalse($lesson->isActive());
        }
    }

    public function testCreateAdminFilterQueryBuilderFiltersByCursusId(): void
    {
        $cursus = $this->getGuitarCursusManaged();

        $results = $this->repo->createAdminFilterQueryBuilder(null, 'all', $cursus->getId())->getQuery()->getResult();
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

        $results = $this->repo->createAdminFilterQueryBuilder(null, 'all', null, $themeId)->getQuery()->getResult();
        self::assertNotEmpty($results);

        foreach ($results as $l) {
            self::assertSame($themeId, $l->getCursus()->getTheme()->getId());
        }
    }

    public function testCreateAdminFilterQueryBuilderSortTitleAscAndDesc(): void
    {
        $asc = $this->repo->createAdminFilterQueryBuilder(null, 'all', null, null, 'title_asc')->getQuery()->getResult();
        self::assertGreaterThanOrEqual(2, count($asc));

        $titlesAsc = array_map(fn (Lesson $l) => (string) $l->getTitle(), $asc);
        $sortedAsc = $titlesAsc;
        sort($sortedAsc, SORT_STRING);
        self::assertSame($sortedAsc, $titlesAsc);

        $desc = $this->repo->createAdminFilterQueryBuilder(null, 'all', null, null, 'title_desc')->getQuery()->getResult();
        self::assertGreaterThanOrEqual(2, count($desc));

        $titlesDesc = array_map(fn (Lesson $l) => (string) $l->getTitle(), $desc);
        $sortedDesc = $titlesDesc;
        rsort($sortedDesc, SORT_STRING);
        self::assertSame($sortedDesc, $titlesDesc);
    }

    public function testCreateAdminFilterQueryBuilderSortPriceAscAndDesc(): void
    {
        $asc = $this->repo->createAdminFilterQueryBuilder(null, 'all', null, null, 'price_asc')->getQuery()->getResult();
        self::assertGreaterThanOrEqual(2, count($asc));

        $pricesAsc = array_map(fn (Lesson $l) => $l->getPrice(), $asc);
        $sortedAsc = $pricesAsc;
        sort($sortedAsc);
        self::assertSame($sortedAsc, $pricesAsc);

        $desc = $this->repo->createAdminFilterQueryBuilder(null, 'all', null, null, 'price_desc')->getQuery()->getResult();
        self::assertGreaterThanOrEqual(2, count($desc));

        $pricesDesc = array_map(fn (Lesson $l) => $l->getPrice(), $desc);
        $sortedDesc = $pricesDesc;
        rsort($sortedDesc);
        self::assertSame($sortedDesc, $pricesDesc);
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