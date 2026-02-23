<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Lesson;
use App\Entity\Certification;
use App\Entity\Purchase;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserProperties()
    {
        $user = new User();

        // --- Email ---
        $user->setEmail('test@example.com');
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame('test@example.com', $user->getUserIdentifier());

        // --- Firstname / Lastname ---
        // --- Firstname / Lastname ---
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $this->assertSame('John', $user->getFirstName());
        $this->assertSame('Doe', $user->getLastName());

        // --- Roles ---
        $user->setRoles(['ROLE_ADMIN']);
        $roles = $user->getRoles();
        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles); // ROLE_USER added automatically
        $this->assertCount(2, $roles);

        // --- Password ---
        $user->setPassword('securepassword');
        $this->assertSame('securepassword', $user->getPassword());

        // --- isVerified ---
        $user->setIsVerified(true);
        $this->assertTrue($user->isVerified());
        $user->setIsVerified(false);
        $this->assertFalse($user->isVerified());

        // --- Verification Token ---
        $user->setVerificationToken('token123');
        $this->assertSame('token123', $user->getVerificationToken());

        $expiresAt = new \DateTime('+1 day');
        $user->setVerificationTokenExpiresAt($expiresAt);
        $this->assertSame($expiresAt, $user->getVerificationTokenExpiresAt());
    }

    public function testUserCollections()
    {
        $user = new User();

        // --- Purchases ---
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('setUser')->willReturnSelf();
        $user->addPurchase($purchase);
        $this->assertCount(1, $user->getPurchases());
        $user->removePurchase($purchase);
        $this->assertCount(0, $user->getPurchases());

        // --- Certifications ---
        $certification = $this->createMock(Certification::class);
        $certification->method('setUser')->willReturnSelf();
        $user->addCertification($certification);
        $this->assertCount(1, $user->getCertifications());
        $user->removeCertification($certification);
        $this->assertCount(0, $user->getCertifications());

        // --- Completed Lessons ---
        $lesson = $this->createMock(Lesson::class);
        $user->addCompletedLesson($lesson);
        $this->assertCount(1, $user->getCompletedLessons());
        $user->removeCompletedLesson($lesson);
        $this->assertCount(0, $user->getCompletedLessons());
    }

    public function testCompletedLessons()
    {
        $user = new User();
        $lesson = new Lesson();

        $user->addCompletedLesson($lesson);
        $this->assertContains($lesson, $user->getCompletedLessons());

        $user->removeCompletedLesson($lesson);
        $this->assertNotContains($lesson, $user->getCompletedLessons());
    }

    public function testIdInitiallyNull()
    {
        $user = new User();
        $this->assertNull($user->getId());
    }
}