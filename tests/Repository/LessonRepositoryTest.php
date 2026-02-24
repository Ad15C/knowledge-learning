<?php

namespace App\Tests\Repository;

use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LessonRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ReferenceRepository $refRepo;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $executor = $databaseTool->loadFixtures([ThemeFixtures::class]);

        $this->refRepo = $executor->getReferenceRepository();
    }

    private function getGuitarCursus(): Cursus
    {
        /** @var Cursus $cursus */
        $cursus = $this->refRepo->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);
        $cursus = $this->em->getRepository(Cursus::class)->find($cursus->getId());

        self::assertNotNull($cursus);
        return $cursus;
    }

    public function testReadLessonFromFixturesByReference(): void
    {
        /** @var Lesson $lesson */
        $lesson = $this->refRepo->getReference(ThemeFixtures::LESSON_GUITAR_1_REF, Lesson::class);
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());

        self::assertNotNull($lesson);
        self::assertSame('Découverte de l’instrument', $lesson->getTitle());
        self::assertNotNull($lesson->getCursus());
        self::assertSame('Cursus d’initiation à la guitare', $lesson->getCursus()->getName());
    }

    public function testCRUDCreateReadUpdateDelete(): void
    {
        $cursus = $this->getGuitarCursus();

        // ---------- C (Create) ----------
        $lesson = new Lesson();
        $lesson->setTitle('Leçon CRUD Test')
            ->setPrice(12.3456) // doit arrondir à 12.35
            ->setCursus($cursus)
            ->setFiche('Fiche initiale')
            ->setVideoUrl('https://example.com/initial')
            ->setImage('initial.jpg');

        $this->em->persist($lesson);
        $this->em->flush();

        self::assertNotNull($lesson->getId());

        $lessonId = $lesson->getId();

        // ---------- R (Read) ----------
        $this->em->clear();

        $repo = $this->em->getRepository(Lesson::class);

        /** @var Lesson|null $found */
        $found = $repo->find($lessonId);
        self::assertNotNull($found);

        self::assertSame('Leçon CRUD Test', $found->getTitle());
        self::assertSame(12.35, $found->getPrice());
        self::assertSame('Fiche initiale', $found->getFiche());
        self::assertSame('https://example.com/initial', $found->getVideoUrl());
        self::assertSame('initial.jpg', $found->getImage());
        self::assertNotNull($found->getCursus());
        self::assertSame($cursus->getId(), $found->getCursus()->getId());

        // ---------- U (Update) ----------
        $found->setTitle('Leçon CRUD Test (Updated)')
            ->setPrice(99.999) // => 100.00
            ->setFiche('Fiche modifiée')
            ->setVideoUrl('https://example.com/updated')
            ->setImage('updated.jpg');

        $this->em->flush();
        $this->em->clear();

        /** @var Lesson|null $updated */
        $updated = $repo->find($lessonId);
        self::assertNotNull($updated);

        self::assertSame('Leçon CRUD Test (Updated)', $updated->getTitle());
        self::assertSame(100.00, $updated->getPrice());
        self::assertSame('Fiche modifiée', $updated->getFiche());
        self::assertSame('https://example.com/updated', $updated->getVideoUrl());
        self::assertSame('updated.jpg', $updated->getImage());

        // ---------- D (Delete) ----------
        $this->em->remove($updated);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($repo->find($lessonId));
    }
}