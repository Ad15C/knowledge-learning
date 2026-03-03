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
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);

        $executor = $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        $this->refRepo = $executor->getReferenceRepository();
        $this->em->clear();
    }

    private function getUser(): User
    {
        /** @var User $userRef */
        $userRef = $this->refRepo->getReference(TestUserFixtures::USER_REF, User::class);
        $user = $this->em->getRepository(User::class)->find($userRef->getId());

        self::assertNotNull($user);
        return $user;
    }

    private function getLesson(): Lesson
    {
        /** @var Lesson $lessonRef */
        $lessonRef = $this->refRepo->getReference(ThemeFixtures::LESSON_GUITAR_1_REF, Lesson::class);
        $lesson = $this->em->getRepository(Lesson::class)->find($lessonRef->getId());

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

        self::assertSame($user->getId(), $found->getUser()->getId());
        self::assertSame($lesson->getId(), $found->getLesson()->getId());
        self::assertTrue($found->isCompleted());
        self::assertInstanceOf(\DateTimeImmutable::class, $found->getValidatedAt());

        // Read via findOneBy(user, lesson)
        $found2 = $repo->findOneBy([
            'user' => $found->getUser(),
            'lesson' => $found->getLesson(),
        ]);
        self::assertNotNull($found2);
        self::assertSame($lvId, $found2->getId());

        // ---------- U (Update) ----------
        $before = $found->getValidatedAt();
        self::assertInstanceOf(\DateTimeImmutable::class, $before);

        $found->markCompleted();
        $this->em->flush();
        $this->em->clear();

        /** @var LessonValidated|null $updated */
        $updated = $repo->find($lvId);
        self::assertNotNull($updated);

        $after = $updated->getValidatedAt();
        self::assertInstanceOf(\DateTimeImmutable::class, $after);

        // robuste : nouvelle instance (DateTimeImmutable)
        self::assertNotSame($before, $after);
        self::assertTrue($updated->isCompleted());

        // ---------- D (Delete) ----------
        $this->em->remove($updated);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($repo->find($lvId));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset($this->em)) {
            $this->em->close();
        }
        unset($this->em, $this->refRepo);
        self::ensureKernelShutdown();
    }
}