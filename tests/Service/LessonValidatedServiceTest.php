<?php

namespace App\Tests\Service;

use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use App\Entity\User;
use App\Repository\LessonValidatedRepository;
use App\Service\LessonValidatedService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LessonValidatedServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LessonValidatedService $service;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $repo = static::getContainer()->get(LessonValidatedRepository::class);
        $this->service = new LessonValidatedService($this->em, $repo);
    }

    private function makeUser(string $email = 'u@test.com'): User
    {
        $u = new User();
        $u->setEmail($email)
            ->setPassword('hash')
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
        $c->setName($name)->setPrice(100)->setTheme($theme);
        $this->em->persist($c);
        return $c;
    }

    private function makeLesson(Cursus $cursus, string $title): Lesson
    {
        $l = new Lesson();
        $l->setTitle($title)->setPrice(10)->setCursus($cursus);
        $this->em->persist($l);
        return $l;
    }

    public function testValidateLessonCreatesLessonCertification(): void
    {
        $user = $this->makeUser();
        $theme = $this->makeTheme();
        $cursus = $this->makeCursus($theme);
        $lesson = $this->makeLesson($cursus, 'L1');

        $this->em->flush();

        $this->service->validateLesson($user, $lesson);

        $cert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);

        self::assertNotNull($cert);
        self::assertNotEmpty($cert->getCertificateCode());
        self::assertInstanceOf(\DateTimeInterface::class, $cert->getIssuedAt());
    }

    public function testValidateLessonCreatesThemeAndCursusCertificationWhenAllLessonsCompleted(): void
    {
        $user = $this->makeUser();
        $theme = $this->makeTheme('Theme X');
        $cursus = $this->makeCursus($theme, 'Cursus X');

        $lesson1 = $this->makeLesson($cursus, 'L1');
        $lesson2 = $this->makeLesson($cursus, 'L2');

        $this->em->flush();

        // Valide L1 => cert lesson only
        $this->service->validateLesson($user, $lesson1);

        $themeCert1 = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'theme' => $theme,
            'type' => 'theme',
        ]);
        self::assertNull($themeCert1);

        $cursusCert1 = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);
        self::assertNull($cursusCert1);

        // Valide L2 => theme + cursus devraient se débloquer
        $this->service->validateLesson($user, $lesson2);

        $themeCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'theme' => $theme,
            'type' => 'theme',
        ]);
        self::assertNotNull($themeCert);
        self::assertSame('theme', $themeCert->getType());
        self::assertNotEmpty($themeCert->getCertificateCode());

        $cursusCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);
        self::assertNotNull($cursusCert);
        self::assertSame('cursus', $cursusCert->getType());
        self::assertNotEmpty($cursusCert->getCertificateCode());
    }

    public function testValidateLessonDoesNotDuplicateCertifications(): void
    {
        $user = $this->makeUser();
        $theme = $this->makeTheme();
        $cursus = $this->makeCursus($theme);
        $lesson = $this->makeLesson($cursus, 'L1');

        $this->em->flush();

        $this->service->validateLesson($user, $lesson);
        $this->service->validateLesson($user, $lesson); // 2e fois

        $certs = $this->em->getRepository(Certification::class)->findBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);

        self::assertCount(1, $certs);
    }
}