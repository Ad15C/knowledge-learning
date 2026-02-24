<?php

namespace App\Tests\Repository;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\User;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LessonValidatedCrudTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ReferenceRepository $refRepo;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $executor = $databaseTool->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        $this->refRepo = $executor->getReferenceRepository();
    }

    private function getUser(): User
    {
        $user = $this->refRepo->getReference(TestUserFixtures::USER_REF, User::class);
        $user = $this->em->getRepository(User::class)->find($user->getId());

        self::assertNotNull($user);
        return $user;
    }

    private function getLesson(): Lesson
    {
        $lesson = $this->refRepo->getReference(ThemeFixtures::LESSON_GUITAR_1_REF, Lesson::class);
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());

        self::assertNotNull($lesson);
        return $lesson;
    }

    public function testCRUDLessonValidatedCreateReadUpdateDelete(): void
    {
        $user = $this->getUser();
        $lesson = $this->getLesson();

        // ---------- C (Create) ----------
        $lv = new LessonValidated();
        $lv->setUser($user)
            ->setLesson($lesson)
            ->setPurchaseItem(null)
            ->markCompleted();

        $this->em->persist($lv);
        $this->em->flush();

        self::assertNotNull($lv->getId());
        $lvId = $lv->getId();

        // ---------- R (Read) ----------
        $this->em->clear();

        $repo = $this->em->getRepository(LessonValidated::class);

        /** @var LessonValidated|null $found */
        $found = $repo->find($lvId);
        self::assertNotNull($found);

        self::assertNotNull($found->getUser());
        self::assertSame($user->getId(), $found->getUser()->getId());

        self::assertNotNull($found->getLesson());
        self::assertSame($lesson->getId(), $found->getLesson()->getId());

        self::assertTrue($found->isCompleted());
        self::assertNotNull($found->getValidatedAt());

        // Read via findOneBy(user, lesson)
        $found2 = $repo->findOneBy([
            'user' => $found->getUser(),
            'lesson' => $found->getLesson(),
        ]);
        self::assertNotNull($found2);
        self::assertSame($lvId, $found2->getId());

        // ---------- U (Update) ----------
        $before = $found->getValidatedAt();
        self::assertInstanceOf(\DateTimeInterface::class, $before);

        // on force une nouvelle date (markCompleted)
        usleep(1100000); // 1.1s pour garantir un timestamp différent si tu compares en secondes
        $found->markCompleted();

        $this->em->flush();
        $this->em->clear();

        /** @var LessonValidated|null $updated */
        $updated = $repo->find($lvId);
        self::assertNotNull($updated);

        $after = $updated->getValidatedAt();
        self::assertInstanceOf(\DateTimeInterface::class, $after);

        self::assertTrue($updated->isCompleted());
        self::assertGreaterThan($before->getTimestamp(), $after->getTimestamp());

        // ---------- D (Delete) ----------
        $this->em->remove($updated);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($repo->find($lvId));
    }
}