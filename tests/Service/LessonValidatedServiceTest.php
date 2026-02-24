<?php

namespace App\Tests\Service;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Certification;
use App\Entity\Lesson;
use App\Entity\Theme;
use App\Entity\User;
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

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(LessonValidatedService::class);

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

    private function getLesson(string $ref): Lesson
    {
        $lesson = $this->refRepo->getReference($ref, Lesson::class);
        return $this->em->getRepository(Lesson::class)->find($lesson->getId());
    }

    private function getTheme(string $ref): Theme
    {
        $theme = $this->refRepo->getReference($ref, Theme::class);
        return $this->em->getRepository(Theme::class)->find($theme->getId());
    }

    public function testValidateLessonCreatesLessonValidatedAndLessonCertification(): void
    {
        $user = $this->getUser();
        $lesson = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);

        $this->service->validateLesson($user, $lesson);

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
    }

    public function testCompletingAllLessonsInOneCursusCreatesCursusCertification(): void
    {
        $user = $this->getUser();

        // Cursus guitare = LESSON_GUITAR_1_REF + LESSON_GUITAR_2_REF
        $l1 = $this->getLesson(ThemeFixtures::LESSON_GUITAR_1_REF);
        $l2 = $this->getLesson(ThemeFixtures::LESSON_GUITAR_2_REF);

        $cursus = $l1->getCursus();
        self::assertNotNull($cursus);

        // Valider l1 et l2
        $this->service->validateLesson($user, $l1);
        $this->service->validateLesson($user, $l2);

        $cert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);

        self::assertNotNull($cert);
        self::assertNotEmpty($cert->getCertificateCode());
    }

    public function testCompletingAllLessonsInThemeCreatesThemeCertification(): void
    {
        $user = $this->getUser();
        $theme = $this->getTheme(ThemeFixtures::THEME_MUSIQUE_REF);

        // On récupère toutes les leçons du thème Musique
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
    }
}