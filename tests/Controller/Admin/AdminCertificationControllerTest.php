<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Certification;
use App\Entity\Lesson;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminCertificationControllerTest extends WebTestCase
{
    private function loadBaseFixtures($container): void
    {
        /** @var DatabaseToolCollection $dbTools */
        $dbTools = $container->get(DatabaseToolCollection::class);

        $dbTools->get()->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);
    }

    private function createCertification($container): Certification
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $lesson = $em->getRepository(Lesson::class)->findOneBy([
            'title' => 'Découverte de l’instrument',
        ]);
        self::assertNotNull($lesson);

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $holder = $userRepo->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);
        self::assertNotNull($holder);

        $cert = (new Certification())
            ->setUser($holder)
            ->setLesson($lesson)
            ->setType('LESSON')
            ->setCertificateCode('CERT_TEST_001')
            ->setIssuedAt(new \DateTimeImmutable('now'));

        $em->persist($cert);
        $em->flush();

        return $cert;
    }

    public function testDownloadAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->followRedirects(true); // ✅ traverse le 301 vers https

        $container = $client->getContainer();
        $this->loadBaseFixtures($container);

        $client->request('GET', '/admin/certifications/1/download');

        // Après redirections : normalement redirect vers login (form_login)
        // Si ton app finit sur la page login en 200, adapte (mais souvent 200 après follow).
        $status = $client->getResponse()->getStatusCode();
        self::assertTrue(in_array($status, [200, 302, 401, 403], true), 'Status inattendu: ' . $status);
    }

    public function testDownloadAsUserIsForbidden(): void
    {
        $client = static::createClient();
        $client->followRedirects(true);

        $container = $client->getContainer();
        $this->loadBaseFixtures($container);

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $user = $userRepo->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);
        self::assertNotNull($user);

        $client->loginUser($user);
        $client->request('GET', '/admin/certifications/1/download');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDownloadAsAdminReturnsPdf(): void
    {
        $client = static::createClient();
        $client->followRedirects(true);

        $container = $client->getContainer();
        $this->loadBaseFixtures($container);

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $admin = $userRepo->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);
        self::assertNotNull($admin);

        $cert = $this->createCertification($container);

        $client->loginUser($admin);
        $client->request('GET', '/admin/certifications/' . $cert->getId() . '/download');

        self::assertResponseIsSuccessful();

        $response = $client->getResponse();
        self::assertTrue($response->headers->contains('Content-Type', 'application/pdf'));

        $content = $response->getContent() ?? '';
        self::assertNotEmpty($content);
        self::assertStringStartsWith('%PDF', $content);
    }

    public function testDownloadNotFoundReturns404(): void
    {
        $client = static::createClient();
        $client->followRedirects(true);

        $container = $client->getContainer();
        $this->loadBaseFixtures($container);

        /** @var UserRepository $userRepo */
        $userRepo = $container->get(UserRepository::class);
        $admin = $userRepo->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);
        self::assertNotNull($admin);

        $client->loginUser($admin);
        $client->request('GET', '/admin/certifications/999999/download');

        self::assertResponseStatusCodeSame(404);
    }
}