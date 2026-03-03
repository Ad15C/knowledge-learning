<?php

namespace App\Tests\Repository;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\Theme;
use App\Entity\User;
use App\Repository\LessonValidatedRepository;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LessonValidatedRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LessonValidatedRepository $repo;
    private ReferenceRepository $refRepo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        $executor = $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        $this->refRepo = $executor->getReferenceRepository();
        $this->em = $container->get(EntityManagerInterface::class);

        // IMPORTANT: récupérer le repository custom
        $repo = $this->em->getRepository(LessonValidated::class);
        self::assertInstanceOf(LessonValidatedRepository::class, $repo);
        $this->repo = $repo;

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

    private function getThemeMusique(): Theme
    {
        /** @var Theme $themeRef */
        $themeRef = $this->refRepo->getReference(ThemeFixtures::THEME_MUSIQUE_REF, Theme::class);
        $theme = $this->em->getRepository(Theme::class)->find($themeRef->getId());

        self::assertNotNull($theme);
        return $theme;
    }

    private function getLessonByRef(string $ref): Lesson
    {
        /** @var Lesson $lessonRef */
        $lessonRef = $this->refRepo->getReference($ref, Lesson::class);
        $lesson = $this->em->getRepository(Lesson::class)->find($lessonRef->getId());

        self::assertNotNull($lesson);
        return $lesson;
    }

    private function validateLesson(User $user, Lesson $lesson): void
    {
        $lv = new LessonValidated();
        $lv->setUser($user)
            ->setLesson($lesson)
            ->setPurchaseItem(null)
            ->markCompleted();

        $this->em->persist($lv);
    }

    public function testHasCompletedThemeFalseWhenNoLessonValidated(): void
    {
        $user = $this->getUser();
        $theme = $this->getThemeMusique();

        self::assertFalse($this->repo->hasCompletedTheme($user, $theme));
    }

    public function testHasCompletedThemeFalseWhenOnlyOneLessonValidated(): void
    {
        $user = $this->getUser();
        $theme = $this->getThemeMusique();

        $lesson = $this->getLessonByRef(ThemeFixtures::LESSON_GUITAR_1_REF);

        $this->validateLesson($user, $lesson);
        $this->em->flush();
        $this->em->clear();

        self::assertFalse($this->repo->hasCompletedTheme($user, $theme));
    }

    public function testHasCompletedThemeTrueWhenAllThemeLessonsValidated(): void
    {
        $user = $this->getUser();
        $theme = $this->getThemeMusique();

        $lessons = $this->em->getRepository(Lesson::class)
            ->createQueryBuilder('l')
            ->join('l.cursus', 'c')
            ->join('c.theme', 't')
            ->andWhere('t.id = :themeId')
            ->setParameter('themeId', $theme->getId())
            ->getQuery()
            ->getResult();

        self::assertNotEmpty($lessons, 'Le thème Musique doit contenir des leçons via fixtures.');

        foreach ($lessons as $lesson) {
            self::assertInstanceOf(Lesson::class, $lesson);
            $this->validateLesson($user, $lesson);
        }

        $this->em->flush();
        $this->em->clear();

        self::assertTrue($this->repo->hasCompletedTheme($user, $theme));
    }

    public function testHasCompletedThemeReturnsFalseForEmptyTheme(): void
    {
        $user = $this->getUser();

        $emptyTheme = new Theme();
        $emptyTheme->setName('Theme Vide');

        $this->em->persist($emptyTheme);
        $this->em->flush();
        $this->em->clear();

        $emptyThemeReloaded = $this->em->getRepository(Theme::class)->find($emptyTheme->getId());
        self::assertNotNull($emptyThemeReloaded);

        self::assertFalse($this->repo->hasCompletedTheme($user, $emptyThemeReloaded));
    }

    public function testFindValidatedLessonsForUserOrdersByValidatedAtDescAndJoins(): void
    {
        $user = $this->getUser();

        $l1 = $this->getLessonByRef(ThemeFixtures::LESSON_GUITAR_1_REF);
        $l2 = $this->getLessonByRef(ThemeFixtures::LESSON_GUITAR_2_REF);

        // Crée 2 validations, puis on force des dates (ordre déterministe)
        $lv1 = new LessonValidated();
        $lv1->setUser($user)->setLesson($l1)->setPurchaseItem(null)->markCompleted();

        $lv2 = new LessonValidated();
        $lv2->setUser($user)->setLesson($l2)->setPurchaseItem(null)->markCompleted();

        // Forcer validatedAt sans usleep (pour vérifier le tri)
        $ref1 = new \ReflectionClass($lv1);
        $p1 = $ref1->getProperty('validatedAt');
        $p1->setAccessible(true);
        $p1->setValue($lv1, new \DateTimeImmutable('2026-01-01 10:00:00'));

        $ref2 = new \ReflectionClass($lv2);
        $p2 = $ref2->getProperty('validatedAt');
        $p2->setAccessible(true);
        $p2->setValue($lv2, new \DateTimeImmutable('2026-02-01 10:00:00'));

        $this->em->persist($lv1);
        $this->em->persist($lv2);
        $this->em->flush();
        $this->em->clear();

        $rows = $this->repo->findValidatedLessonsForUser($user);

        self::assertCount(2, $rows);
        self::assertSame($l2->getId(), $rows[0]->getLesson()->getId()); // 2026-02-01 d'abord
        self::assertSame($l1->getId(), $rows[1]->getLesson()->getId());

        // joins: lesson -> cursus -> theme
        self::assertNotNull($rows[0]->getLesson());
        self::assertNotNull($rows[0]->getLesson()->getCursus());
        self::assertNotNull($rows[0]->getLesson()->getCursus()->getTheme());
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