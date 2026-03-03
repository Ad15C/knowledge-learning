<?php

namespace App\Tests\Entity;

use App\Entity\Certification;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testDefaultsOnConstruct(): void
    {
        $user = new User();

        self::assertNull($user->getId());
        self::assertNull($user->getEmail());
        self::assertNull($user->getPassword());
        self::assertNull($user->getPlainPassword());

        // createdAt est set dans __construct()
        self::assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        self::assertNull($user->getArchivedAt());
        self::assertTrue($user->isActive());
        self::assertFalse($user->isArchived());

        // isVerified default
        self::assertFalse($user->isVerified());

        // roles stockés par défaut = []
        self::assertSame([], $user->getStoredRoles());

        // roles runtime = au moins ROLE_USER
        self::assertContains('ROLE_USER', $user->getRoles());

        // collections
        self::assertCount(0, $user->getPurchases());
        self::assertCount(0, $user->getCertifications());
        self::assertCount(0, $user->getLessonValidated());
        self::assertCount(0, $user->getCompletedLessons());
    }

    public function testUserProperties(): void
    {
        $user = new User();

        // --- Email ---
        $user->setEmail('test@example.com');
        self::assertSame('test@example.com', $user->getEmail());
        self::assertSame('test@example.com', $user->getUserIdentifier());

        // --- Firstname / Lastname ---
        $user->setFirstName('John');
        $user->setLastName('Doe');
        self::assertSame('John', $user->getFirstName());
        self::assertSame('Doe', $user->getLastName());

        // --- Password ---
        $user->setPassword('securepassword');
        self::assertSame('securepassword', $user->getPassword());

        // --- isVerified ---
        $user->setIsVerified(true);
        self::assertTrue($user->isVerified());
        $user->setIsVerified(false);
        self::assertFalse($user->isVerified());

        // --- Verification Token ---
        $user->setVerificationToken('token123');
        self::assertSame('token123', $user->getVerificationToken());

        $expiresAt = new \DateTime('+1 day');
        $user->setVerificationTokenExpiresAt($expiresAt);
        self::assertSame($expiresAt, $user->getVerificationTokenExpiresAt());
    }

    public function testRolesStoredAndRuntime(): void
    {
        $user = new User();

        // Par défaut : storedRoles = []
        self::assertSame([], $user->getStoredRoles());

        // getRoles() ajoute toujours ROLE_USER
        $roles = $user->getRoles();
        self::assertContains('ROLE_USER', $roles);
        self::assertNotContains('ROLE_ADMIN', $roles);

        // setRoles(['ROLE_ADMIN']) => storedRoles = ['ROLE_ADMIN']
        $user->setRoles(['ROLE_ADMIN']);
        self::assertSame(['ROLE_ADMIN'], $user->getStoredRoles());

        $roles = $user->getRoles();
        self::assertContains('ROLE_ADMIN', $roles);
        self::assertContains('ROLE_USER', $roles);
        self::assertCount(2, $roles);

        // setRoles(['ROLE_USER']) => storedRoles doit rester []
        $user->setRoles(['ROLE_USER']);
        self::assertSame([], $user->getStoredRoles());

        $roles = $user->getRoles();
        self::assertContains('ROLE_USER', $roles);
        self::assertNotContains('ROLE_ADMIN', $roles);
    }

    public function testPlainPasswordAndEraseCredentials(): void
    {
        $user = new User();

        self::assertNull($user->getPlainPassword());

        $user->setPlainPassword('MyNewPassword123!');
        self::assertSame('MyNewPassword123!', $user->getPlainPassword());

        $user->eraseCredentials();
        self::assertNull($user->getPlainPassword());
    }

    public function testArchivedFlags(): void
    {
        $user = new User();

        self::assertNull($user->getArchivedAt());
        self::assertTrue($user->isActive());
        self::assertFalse($user->isArchived());

        $dt = new \DateTimeImmutable('2026-03-01 10:00:00');
        $user->setArchivedAt($dt);

        self::assertSame($dt, $user->getArchivedAt());
        self::assertFalse($user->isActive());
        self::assertTrue($user->isArchived());

        // remise à null = actif
        $user->setArchivedAt(null);
        self::assertNull($user->getArchivedAt());
        self::assertTrue($user->isActive());
        self::assertFalse($user->isArchived());
    }

    public function testUserCollections(): void
    {
        $user = new User();

        // --- Purchases ---
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('setUser')->willReturnSelf();

        $user->addPurchase($purchase);
        self::assertCount(1, $user->getPurchases());

        $user->removePurchase($purchase);
        self::assertCount(0, $user->getPurchases());

        // --- Certifications ---
        $certification = $this->createMock(Certification::class);
        $certification->method('setUser')->willReturnSelf();

        $user->addCertification($certification);
        self::assertCount(1, $user->getCertifications());

        $user->removeCertification($certification);
        self::assertCount(0, $user->getCertifications());

        // --- Completed Lessons ---
        $lesson = $this->createMock(Lesson::class);

        $user->addCompletedLesson($lesson);
        self::assertCount(1, $user->getCompletedLessons());

        // pas de doublons
        $user->addCompletedLesson($lesson);
        self::assertCount(1, $user->getCompletedLessons());

        $user->removeCompletedLesson($lesson);
        self::assertCount(0, $user->getCompletedLessons());
    }
}