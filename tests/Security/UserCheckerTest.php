<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserCheckerTest extends TestCase
{
    private function createBaseUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

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
        // pas archivé
        $user->setArchivedAt(null);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte n’est pas encore vérifié.');

        $checker->checkPreAuth($user);
    }

    public function testAllowsVerifiedAndActiveUser(): void
    {
        $checker = new UserChecker();
        $user = $this->createBaseUser();

        $user->setIsVerified(true);
        $user->setArchivedAt(null);

        // Ne doit PAS lever d’exception
        $checker->checkPreAuth($user);

        $this->assertTrue(true); // si on arrive ici, c'est OK
    }
}