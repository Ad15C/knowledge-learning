<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class VerificationControllerTest extends WebTestCase
{
    use DoctrineSchemaTrait;

    private EntityManagerInterface $em;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        // Un SEUL client pour tout le test (sinon erreur "booted twice")
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($this->em);
    }

    public function testVerifyEmailWithInvalidToken(): void
    {
        $this->client->request('GET', '/verify-email?token=invalid');
        $this->assertResponseRedirects('/login');
    }

    public function testVerifyEmailWithExpiredToken(): void
    {
        $user = new User();
        $user->setEmail('exp@example.com');
        $user->setFirstName('Exp');
        $user->setLastName('Ired');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('hashed');
        $user->setIsVerified(false);
        $user->setVerificationToken('tok');
        $user->setVerificationTokenExpiresAt(new \DateTime('-1 day'));

        $this->em->persist($user);
        $this->em->flush();

        $this->client->request('GET', '/verify-email?token=tok');
        $this->assertResponseRedirects('/login');

        $this->em->refresh($user);
        $this->assertFalse($user->isVerified());
        $this->assertSame('tok', $user->getVerificationToken());
    }

    public function testVerifyEmailWithValidToken(): void
    {
        $user = new User();
        $user->setEmail('ok@example.com');
        $user->setFirstName('Ok');
        $user->setLastName('User');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('hashed');
        $user->setIsVerified(false);
        $user->setVerificationToken('validtok');
        $user->setVerificationTokenExpiresAt(new \DateTime('+1 day'));

        $this->em->persist($user);
        $this->em->flush();

        $this->client->request('GET', '/verify-email?token=validtok');
        $this->assertResponseRedirects('/login');

        $this->em->refresh($user);
        $this->assertTrue($user->isVerified());
        $this->assertNull($user->getVerificationToken());
        $this->assertNull($user->getVerificationTokenExpiresAt());
    }
}