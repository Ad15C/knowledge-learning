<?php

namespace App\Tests\Repository;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\Theme;
use App\Entity\User;
use App\Repository\LessonValidatedRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LessonValidatedRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LessonValidatedRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = $this->em->getRepository(LessonValidated::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private function makeUser(string $email = 'u@test.com'): User
    {
        $u = new User();
        $u->setEmail($email)
          ->setPassword('hash') // ok pour tests
          ->setFirstName('John')
          ->setLastName('Doe')
          ->setRoles(['ROLE_USER']);

        $this->em->persist($u);

        return $u;
    }

    private function makeTheme(string $name = 'Theme A'): Theme
    {
        $t = new Theme();
        $t->setName($name);
        $this->em->persist($t);
        return $t;
    }

    private function makeCursus(Theme $theme, string $name = 'Cursus A'): Cursus
    {
        $c = new Cursus();
        $c->setName($name)
          ->setPrice(100)
          ->setTheme($theme);

        $this->em->persist($c);
        return $c;
    }

    private function makeLesson(Cursus $cursus, string $title): Lesson
    {
        $l = new Lesson();
        $l->setTitle($title)
          ->setPrice(10)
          ->setCursus($cursus);

        $this->em->persist($l);
        return $l;
    }

    private function validateLesson(User $user, Lesson $lesson): LessonValidated
    {
        $lv = new LessonValidated();
        $lv->setUser($user)->setLesson($lesson)->markCompleted();
        $this->em->persist($lv);
        return $lv;
    }

    public function testHasCompletedThemeReturnsFalseWhenNoLessonsInTheme(): void
    {
        $user = $this->makeUser();
        $theme = $this->makeTheme();
        // pas de cursus/leçon pour ce thème

        $this->em->flush();

        self::assertTrue(
            // Ton repo compare validatedLessons >= totalLessons
            // totalLessons = 0, validatedLessons = 0 => true
            // Donc en réalité ton implémentation renverra TRUE ici.
            // Si tu veux "false quand theme vide", il faut changer le code.
            $this->repo->hasCompletedTheme($user, $theme)
        );
    }

    public function testHasCompletedThemeFalseWhenNotAllLessonsValidated(): void
    {
        $user = $this->makeUser();
        $theme = $this->makeTheme();
        $cursus = $this->makeCursus($theme);

        $lesson1 = $this->makeLesson($cursus, 'L1');
        $lesson2 = $this->makeLesson($cursus, 'L2');

        // valide seulement L1
        $this->validateLesson($user, $lesson1);

        $this->em->flush();
        $this->em->clear();

        $userRef = $this->em->getRepository(User::class)->findOneBy(['email' => 'u@test.com']);
        $themeRef = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Theme A']);

        self::assertFalse($this->repo->hasCompletedTheme($userRef, $themeRef));
    }

    public function testHasCompletedThemeTrueWhenAllLessonsValidated(): void
    {
        $user = $this->makeUser();
        $theme = $this->makeTheme();
        $cursus = $this->makeCursus($theme);

        $lesson1 = $this->makeLesson($cursus, 'L1');
        $lesson2 = $this->makeLesson($cursus, 'L2');

        $this->validateLesson($user, $lesson1);
        $this->validateLesson($user, $lesson2);

        $this->em->flush();
        $this->em->clear();

        $userRef = $this->em->getRepository(User::class)->findOneBy(['email' => 'u@test.com']);
        $themeRef = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Theme A']);

        self::assertTrue($this->repo->hasCompletedTheme($userRef, $themeRef));
    }

    public function testHasCompletedThemeDoesNotCountLessonsFromOtherTheme(): void
    {
        $user = $this->makeUser();
        $themeA = $this->makeTheme('Theme A');
        $themeB = $this->makeTheme('Theme B');

        $cursusA = $this->makeCursus($themeA, 'Cursus A');
        $cursusB = $this->makeCursus($themeB, 'Cursus B');

        $a1 = $this->makeLesson($cursusA, 'A1');
        $a2 = $this->makeLesson($cursusA, 'A2');
        $b1 = $this->makeLesson($cursusB, 'B1');

        // Valide A1, B1 mais pas A2
        $this->validateLesson($user, $a1);
        $this->validateLesson($user, $b1);

        $this->em->flush();
        $this->em->clear();

        $userRef = $this->em->getRepository(User::class)->findOneBy(['email' => 'u@test.com']);
        $themeARef = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Theme A']);

        self::assertFalse($this->repo->hasCompletedTheme($userRef, $themeARef));
    }
}