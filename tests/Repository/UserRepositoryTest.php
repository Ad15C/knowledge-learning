<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()
            ->get(EntityManagerInterface::class);

        $this->repository = $this->em->getRepository(User::class);

        // Nettoyer tous les utilisateurs existants
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
    }

    private function createUser(string $email = 'user@test.com'): User
    {
        $user = new User();

        $user->setEmail($email)
             ->setPassword('password_hash')
             ->setFirstname('John')
             ->setLastname('Doe');

        return $user;
    }

    public function testCreateUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com')
             ->setPassword('password')
             ->setFirstname('John')
             ->setLastname('Doe');

        $this->em->persist($user);
        $this->em->flush();

        $this->assertNotNull($user->getId());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testFindUserByEmail(): void
    {
        $user = $this->createUser('find@test.com');
        $this->em->persist($user);
        $this->em->flush();

        $found = $this->repository->findOneBy([
            'email' => 'find@test.com'
        ]);

        $this->assertInstanceOf(User::class, $found);
        $this->assertEquals('find@test.com', $found->getEmail());
    }

    public function testUpdateUser(): void
    {
        $user = $this->createUser();
        $this->em->persist($user);
        $this->em->flush();

        $user->setFirstname('Updated');
        $this->em->flush();
        $this->em->refresh($user);

        $this->assertEquals('Updated', $user->getFirstname());
    }

    public function testDeleteUser(): void
    {
        $user = new User();
        $user->setEmail('del@test.com')
             ->setPassword('pass')
             ->setFirstname('A')
             ->setLastname('B');

        $this->em->persist($user);
        $this->em->flush();

        $id = $user->getId();

        $this->em->remove($user);
        $this->em->flush();

        $this->assertNull($this->repository->find($id));
    }

    public function testEmailUnique(): void
    {
        $user1 = new User();
        $user1->setEmail('unique@test.com')
              ->setPassword('pass')
              ->setFirstname('A')
              ->setLastname('B');

        $user2 = new User();
        $user2->setEmail('unique@test.com')
              ->setPassword('pass')
              ->setFirstname('C')
              ->setLastname('D');

        $this->em->persist($user1);
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->persist($user2);
        $this->em->flush();
    }

    public function testDefaultRoleIsUser(): void
    {
        $user = $this->createUser();

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testSetRoles(): void
    {
        $user = $this->createUser();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles); // ROLE_USER ajouté par défaut
    }

    public function testUpgradePassword(): void
    {
        $user = new User();
        $user->setEmail('upgrade@test.com')
             ->setPassword('old')
             ->setFirstname('X')
             ->setLastname('Y');

        $this->em->persist($user);
        $this->em->flush();

        $this->repository->upgradePassword($user, 'newHashedPassword');

        $this->em->refresh($user);

        $this->assertEquals('newHashedPassword', $user->getPassword());
    }

    public function testUpgradePasswordThrowsException(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $fakeUser = $this->createMock(PasswordAuthenticatedUserInterface::class);

        $this->repository->upgradePassword($fakeUser, 'password');
    }

    public function testFindAllUsers(): void
    {
        $user1 = $this->createUser('user1@test.com');
        $user2 = $this->createUser('user2@test.com');

        $this->em->persist($user1);
        $this->em->persist($user2);
        $this->em->flush();

        $users = $this->repository->findAll();

        $this->assertGreaterThanOrEqual(2, count($users));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}