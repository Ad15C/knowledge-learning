<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserInterface;

class UserCheckerTest extends TestCase
{
    private UserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserChecker();
    }

    private function makeUser(
        bool $verified = true,
        bool $archived = false,
        array $roles = []
    ): User {
        $user = (new User())
            ->setEmail('test@example.com')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setPassword('hashed-password')
            ->setIsVerified($verified)
            ->setRoles($roles);

        if ($archived) {
            $user->setArchivedAt(new \DateTimeImmutable('-1 day'));
        }

        return $user;
    }

    public function testActiveVerifiedUserPassesPreAuth(): void
    {
        $user = $this->makeUser(true, false);

        $this->checker->checkPreAuth($user);

        $this->assertTrue(true);
    }

    public function testUnverifiedUserIsRejected(): void
    {
        $user = $this->makeUser(false, false);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte n’est pas encore vérifié.');

        $this->checker->checkPreAuth($user);
    }

    public function testArchivedUserIsRejected(): void
    {
        $user = $this->makeUser(true, true);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte est archivé. Contactez un administrateur.');

        $this->checker->checkPreAuth($user);
    }

    public function testUnverifiedAdminIsRejected(): void
    {
        $user = $this->makeUser(false, false, ['ROLE_ADMIN']);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte n’est pas encore vérifié.');

        $this->checker->checkPreAuth($user);
    }

    public function testArchivedAdminIsRejected(): void
    {
        $user = $this->makeUser(true, true, ['ROLE_ADMIN']);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte est archivé. Contactez un administrateur.');

        $this->checker->checkPreAuth($user);
    }

    public function testArchivedAndUnverifiedUserReturnsArchivedMessageFirst(): void
    {
        $user = $this->makeUser(false, true);

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Votre compte est archivé. Contactez un administrateur.');

        $this->checker->checkPreAuth($user);
    }

    public function testIgnoresNonAppUserInstances(): void
    {
        $fake = new class implements UserInterface {
            public function getRoles(): array { return ['ROLE_USER']; }
            public function eraseCredentials(): void {}
            public function getUserIdentifier(): string { return 'fake'; }
        };

        $this->checker->checkPreAuth($fake);

        $this->assertTrue(true);
    }
}