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
        self::assertSame($user->getId(), $lv->getUser()?->getId());
        self::assertSame($lesson->getId(), $lv->getLesson()?->getId());

        $cert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);

        self::assertNotNull($cert);
        self::assertNotEmpty($cert->getCertificateCode());
        self::assertNotNull($cert->getIssuedAt());
    }

    public function testValidateSameLessonDoesNotDuplicateLessonCertification(): void
    {
        $user = $this->getUser();
        $lesson = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);

        $this->service->validateLesson($user, $lesson);

        $this->em->clear();
        $user = $this->getUser();
        $lesson = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);

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

        $lv = new LessonValidated();
        $lv->setUser($user)
            ->setLesson($lesson);

        $this->em->persist($lv);
        $this->em->flush();
        $existingId = $lv->getId();

        $this->em->clear();

        $user = $this->getUser();
        $lesson = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);

        $returned = $this->service->validateLesson($user, $lesson);

        self::assertSame($existingId, $returned->getId());
        self::assertTrue($returned->isCompleted());

        $validation = $this->lessonValidatedRepo->find($existingId);
        self::assertNotNull($validation);
        self::assertTrue($validation->isCompleted());
    }

    public function testCompletingAllLessonsInOneCursusCreatesCursusCertification(): void
    {
        $user = $this->getUser();

        $l1 = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);
        $l2 = $this->getLesson(ThemeFixtures::LESSON_GUITAR_2_REF);

        $cursus = $l1->getCursus();
        self::assertNotNull($cursus);

        $this->service->validateLesson($user, $l1);

        $certBefore = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);
        self::assertNull($certBefore);

        $this->em->clear();
        $user = $this->getUser();
        $l2 = $this->getLesson(ThemeFixtures::LESSON_GUITAR_2_REF);
        $cursus = $l2->getCursus();
        self::assertNotNull($cursus);

        $this->service->validateLesson($user, $l2);

        $cert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);

        self::assertNotNull($cert);
        self::assertNotEmpty($cert->getCertificateCode());
        self::assertNotNull($cert->getIssuedAt());

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

        $lessonIds = $this->em->getRepository(Lesson::class)
            ->createQueryBuilder('l')
            ->select('l.id')
            ->join('l.cursus', 'c')
            ->join('c.theme', 't')
            ->andWhere('t.id = :themeId')
            ->setParameter('themeId', $theme->getId())
            ->orderBy('l.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        self::assertNotEmpty($lessonIds);

        foreach ($lessonIds as $lessonId) {
            $lesson = $this->em->getRepository(Lesson::class)->find($lessonId);

            self::assertNotNull($lesson);
            self::assertInstanceOf(Lesson::class, $lesson);

            $this->service->validateLesson($user, $lesson);

            $this->em->clear();
            $user = $this->getUser();
            $theme = $this->getTheme(ThemeFixtures::THEME_MUSIQUE_REF);
        }

        $themeCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'theme' => $theme,
            'type' => 'theme',
        ]);

        self::assertNotNull($themeCert);
        self::assertNotEmpty($themeCert->getCertificateCode());
        self::assertNotNull($themeCert->getIssuedAt());

        self::assertTrue($this->service->isThemeCompleted($user, $theme));

        $themeCerts = $this->em->getRepository(Certification::class)->findBy([
            'user' => $user,
            'theme' => $theme,
            'type' => 'theme',
        ]);
        self::assertCount(1, $themeCerts);
    }
}