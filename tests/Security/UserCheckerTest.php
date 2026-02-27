<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserCheckerTest extends TestCase
{
    public function testBlocksUnverifiedUser(): void
    {
        $checker = new UserChecker();
        $user = new User();
        $user->setEmail('u@example.com');
        $user->setFirstName('U');
        $user->setLastName('X');
        $user->setPassword('hashed');
        $user->setIsVerified(false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage("Votre compte n’est pas encore vérifié.");
        $checker->checkPreAuth($user);
    }

    public function testAllowsVerifiedUser(): void
    {
        $checker = new UserChecker();
        $user = new User();
        $user->setEmail('v@example.com');
        $user->setFirstName('V');
        $user->setLastName('X');
        $user->setPassword('hashed');
        $user->setIsVerified(true);

        $checker->checkPreAuth($user);
        $this->assertTrue(true);
    }
}