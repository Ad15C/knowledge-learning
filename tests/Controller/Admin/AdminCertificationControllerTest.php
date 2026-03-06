<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Certification;
use App\Entity\Lesson;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminCertificationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient([], [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SERVER_PORT' => 443,
        ]);

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var DatabaseToolCollection $dbTools */
        $dbTools = static::getContainer()->get(DatabaseToolCollection::class);
        $dbTools->get()->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);
    }

    private function getAdmin(): User
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture introuvable.');

        return $admin;
    }

    private function getUser(): User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture introuvable.');

        return $user;
    }

    private function createCertification(): Certification
    {
        $lesson = $this->em->getRepository(Lesson::class)->findOneBy([
            'title' => 'Découverte de l’instrument',
        ]);
        self::assertNotNull($lesson, 'Leçon fixture introuvable.');

        $holder = $this->getUser();

        $cert = (new Certification())
            ->setUser($holder)
            ->setLesson($lesson)
            ->setType('LESSON')
            ->setCertificateCode('CERT_TEST_001')
            ->setIssuedAt(new \DateTimeImmutable());

        $this->em->persist($cert);
        $this->em->flush();

        return $cert;
    }

    public function testDownloadAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', 'https://localhost/admin/certifications/1/download');

        self::assertTrue($this->client->getResponse()->isRedirection());
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
    }

    public function testDownloadAsUserIsForbidden(): void
    {
        $user = $this->getUser();
        $this->client->loginUser($user, 'main');

        $this->client->request('GET', 'https://localhost/admin/certifications/1/download');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDownloadAsAdminReturnsPdf(): void
    {
        $admin = $this->getAdmin();
        $cert = $this->createCertification();

        $this->client->loginUser($admin, 'main');
        $this->client->request('GET', 'https://localhost/admin/certifications/' . $cert->getId() . '/download');

        self::assertResponseIsSuccessful();

        $response = $this->client->getResponse();

        self::assertTrue($response->headers->contains('Content-Type', 'application/pdf'));

        $disposition = (string) $response->headers->get('Content-Disposition');
        self::assertStringContainsString('attachment;', $disposition);
        self::assertStringContainsString('certificat-CERT_TEST_001.pdf', $disposition);

        $content = $response->getContent() ?? '';
        self::assertNotEmpty($content);
        self::assertStringStartsWith('%PDF', $content);
    }

    public function testDownloadNotFoundReturns404(): void
    {
        $admin = $this->getAdmin();

        $this->client->loginUser($admin, 'main');
        $this->client->request('GET', 'https://localhost/admin/certifications/999999/download');

        self::assertResponseStatusCodeSame(404);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}