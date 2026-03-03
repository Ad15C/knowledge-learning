<?php

namespace App\Tests\Service;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Certification;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\Theme;
use App\Entity\User;
use App\Repository\LessonValidatedRepository;
use App\Service\LessonValidatedService;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LessonValidatedServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LessonValidatedService $service;
    private ReferenceRepository $refRepo;
    private LessonValidatedRepository $lessonValidatedRepo;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(LessonValidatedService::class);
        $this->lessonValidatedRepo = static::getContainer()->get(LessonValidatedRepository::class);

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
        $fresh = $this->em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }

    private function getLesson(string $ref): Lesson
    {
        $lesson = $this->refRepo->getReference($ref, Lesson::class);
        $fresh = $this->em->getRepository(Lesson::class)->find($lesson->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }

    private function getTheme(string $ref): Theme
    {
        $theme = $this->refRepo->getReference($ref, Theme::class);
        $fresh = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }

    public function testValidateLessonCreatesLessonValidatedAndLessonCertification(): void
    {
        $user = $this->getUser();
        $lesson = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);

        $lv = $this->service->validateLesson($user, $lesson);

        self::assertInstanceOf(LessonValidated::class, $lv);
        self::assertTrue($lv->isCompleted());

        $cert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);

        self::assertNotNull($cert);
        self::assertNotEmpty($cert->getCertificateCode());
    }

    public function testValidateSameLessonDoesNotDuplicateLessonCertification(): void
    {
        $user = $this->getUser();
        $lesson = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);

        $this->service->validateLesson($user, $lesson);
        $this->service->validateLesson($user, $lesson);

        $certs = $this->em->getRepository(Certification::class)->findBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);

        self::assertCount(1, $certs);

        $validations = $this->lessonValidatedRepo->findBy([
            'user' => $user,
            'lesson' => $lesson,
        ]);
        self::assertCount(1, $validations);
        self::assertTrue($validations[0]->isCompleted());
    }

    public function testValidateLessonMarksExistingIncompleteValidationAsCompleted(): void
    {
        $user = $this->getUser();
        $lesson = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);

        // Crée une validation incomplète manuellement
        $lv = new LessonValidated();
        $lv->setUser($user)->setLesson($lesson);
        // on suppose que par défaut completed=false tant qu'on n'appelle pas markCompleted()
        $this->em->persist($lv);
        $this->em->flush();
        $this->em->clear();

        $user = $this->em->getRepository(User::class)->find($user->getId());
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());
        self::assertNotNull($user);
        self::assertNotNull($lesson);

        $returned = $this->service->validateLesson($user, $lesson);

        self::assertSame($lv->getId(), $returned->getId());
        self::assertTrue($returned->isCompleted());
    }

    public function testCompletingAllLessonsInOneCursusCreatesCursusCertification(): void
    {
        $user = $this->getUser();

        $l1 = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);
        $l2 = $this->getLesson(ThemeFixtures::LESSON_GUITAR_2_REF);

        $cursus = $l1->getCursus();
        self::assertNotNull($cursus);

        $this->service->validateLesson($user, $l1);

        // pas encore complet => pas de cert cursus
        $certBefore = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);
        self::assertNull($certBefore);

        // maintenant complet
        $this->service->validateLesson($user, $l2);

        $cert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);

        self::assertNotNull($cert);
        self::assertNotEmpty($cert->getCertificateCode());

        // pas de duplication
        $certs = $this->em->getRepository(Certification::class)->findBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);
        self::assertCount(1, $certs);
    }

    public function testCompletingAllLessonsInThemeCreatesThemeCertificationAndIsThemeCompleted(): void
    {
        $user = $this->getUser();
        $theme = $this->getTheme(ThemeFixtures::THEME_MUSIQUE_REF);

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
            $this->service->validateLesson($user, $lesson);
        }

        $themeCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'theme' => $theme,
            'type' => 'theme',
        ]);

        self::assertNotNull($themeCert);
        self::assertNotEmpty($themeCert->getCertificateCode());

        // vérifie isThemeCompleted
        self::assertTrue($this->service->isThemeCompleted($user, $theme));

        // pas de duplication
        $themeCerts = $this->em->getRepository(Certification::class)->findBy([
            'user' => $user,
            'theme' => $theme,
            'type' => 'theme',
        ]);
        self::assertCount(1, $themeCerts);
    }
}