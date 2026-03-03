<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    private function createBaseUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

        // Par défaut : actif et non vérifié (on override dans chaque test)
        $user->setArchivedAt(null);
        $user->setIsVerified(false);

        return $user;
    }

    public function testBlocksArchivedUser(): void
    {
        $checker = new UserChecker();
        $user = $this->createBaseUser();

        $user->setIsVerified(true);
        $user->setArchivedAt(new \DateTimeImmutable());

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte est archivé. Contactez un administrateur.');

        $checker->checkPreAuth($user);
    }

    public function testBlocksUnverifiedUser(): void
    {
        $checker = new UserChecker();
        $user = $this->createBaseUser();

        $user->setIsVerified(false);
        $user->setArchivedAt(null);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte n’est pas encore vérifié.');

        $checker->checkPreAuth($user);
    }

    public function testAllowsVerifiedActiveAdmin(): void
    {
        $checker = new UserChecker();
        $admin = $this->createBaseUser();

        // Admin actif + vérifié
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsVerified(true);
        $admin->setArchivedAt(null);

        // Ne doit PAS lever d’exception
        $checker->checkPreAuth($admin);

        $this->assertTrue(true);
    }

    public function testIgnoresNonAppUserInstances(): void
    {
        $checker = new UserChecker();

        // Un "faux user" qui implémente UserInterface, mais pas App\Entity\User
        $fake = new class implements UserInterface {
            public function getRoles(): array { return ['ROLE_USER']; }
            public function eraseCredentials(): void {}
            public function getUserIdentifier(): string { return 'fake'; }
        };

        // Ne doit pas lever d’exception
        $checker->checkPreAuth($fake);

        $this->assertTrue(true);
    }
}