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
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = $this->em->getRepository(LessonValidated::class);

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
        return $this->em->getRepository(User::class)->find($user->getId());
    }

    private function getThemeMusique(): Theme
    {
        $theme = $this->refRepo->getReference(ThemeFixtures::THEME_MUSIQUE_REF, Theme::class);
        return $this->em->getRepository(Theme::class)->find($theme->getId());
    }

    private function validateLesson(User $user, Lesson $lesson): void
    {
        $lv = new LessonValidated();
        $lv->setUser($user)
            ->setLesson($lesson)
            ->markCompleted();

        $this->em->persist($lv);
        $this->em->flush();
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

        $lesson = $this->refRepo->getReference(
            ThemeFixtures::LESSON_GUITAR_1_REF,
            Lesson::class
        );
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());

        $this->validateLesson($user, $lesson);

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

        self::assertNotEmpty($lessons);

        foreach ($lessons as $lesson) {
            $this->validateLesson($user, $lesson);
        }

        self::assertTrue($this->repo->hasCompletedTheme($user, $theme));
    }

    public function testHasCompletedThemeReturnsFalseForEmptyTheme(): void
    {
        $user = $this->getUser();

        $emptyTheme = new Theme();
        $emptyTheme->setName('Theme Vide');

        $this->em->persist($emptyTheme);
        $this->em->flush();

        self::assertFalse($this->repo->hasCompletedTheme($user, $emptyTheme));
    }
}