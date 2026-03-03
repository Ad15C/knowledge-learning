<?php

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class UserRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // ✅ Désactive un éventuel filtre global qui masque les archivés (ex: StofDoctrineExtensions softdeleteable)
        $filters = $this->em->getFilters();
        if ($filters->isEnabled('softdeleteable')) {
            $filters->disable('softdeleteable');
        }

        $repo = $this->em->getRepository(User::class);
        self::assertInstanceOf(UserRepository::class, $repo);
        $this->repository = $repo;

        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
        $this->em->clear();
    }

    private function createUser(
        string $email = 'user@test.com',
        string $firstName = 'John',
        string $lastName = 'Doe',
        array $roles = []
    ): User {
        $user = new User();

        $user->setEmail($email)
            ->setPassword('password_hash')
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRoles($roles);

        return $user;
    }

    private function archiveUser(User $user, \DateTimeImmutable $dt = new \DateTimeImmutable('2026-02-01 10:00:00')): void
    {
        $user->setArchivedAt($dt);
    }

    public function testCreateUser(): void
    {
        $user = $this->createUser('test@example.com', 'John', 'Doe');

        $this->em->persist($user);
        $this->em->flush();

        self::assertNotNull($user->getId());
        self::assertSame('test@example.com', $user->getEmail());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertSame([], $user->getStoredRoles());
    }

    public function testFindUserByEmail(): void
    {
        $user = $this->createUser('find@test.com');
        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        $found = $this->repository->findOneBy(['email' => 'find@test.com']);

        self::assertInstanceOf(User::class, $found);
        self::assertSame('find@test.com', $found->getEmail());
    }

    public function testUpdateUser(): void
    {
        $user = $this->createUser('update@test.com');
        $this->em->persist($user);
        $this->em->flush();

        $user->setFirstName('Updated');
        $this->em->flush();
        $this->em->refresh($user);

        self::assertSame('Updated', $user->getFirstName());
    }

    public function testDeleteUser(): void
    {
        $user = $this->createUser('del@test.com', 'Al', 'Bo');
        $this->em->persist($user);
        $this->em->flush();

        $id = $user->getId();
        self::assertNotNull($id);

        $this->em->remove($user);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->repository->find($id));
    }

    public function testEmailUnique(): void
    {
        $user1 = $this->createUser('unique@test.com', 'Al', 'Bo');
        $user2 = $this->createUser('unique@test.com', 'Cy', 'Do');

        $this->em->persist($user1);
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->persist($user2);
        $this->em->flush();
    }

    public function testRolesNormalization(): void
    {
        $user = $this->createUser('roles@test.com');

        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_ADMIN']);

        self::assertSame(['ROLE_ADMIN'], $user->getStoredRoles());

        $roles = $user->getRoles();
        self::assertContains('ROLE_ADMIN', $roles);
        self::assertContains('ROLE_USER', $roles);
    }

    public function testUpgradePassword(): void
    {
        $user = $this->createUser('upgrade@test.com');
        $this->em->persist($user);
        $this->em->flush();

        $this->repository->upgradePassword($user, 'newHashedPassword');

        $this->em->refresh($user);
        self::assertSame('newHashedPassword', $user->getPassword());
    }

    public function testUpgradePasswordThrowsException(): void
    {
        $this->expectException(UnsupportedUserException::class);

        $fakeUser = $this->createMock(PasswordAuthenticatedUserInterface::class);
        $this->repository->upgradePassword($fakeUser, 'password');
    }

    public function testCountActiveAdmins(): void
    {
        $adminActive = $this->createUser('admin1@test.com', 'Ad', 'Min', ['ROLE_ADMIN']);
        $adminArchived = $this->createUser('admin2@test.com', 'Ad', 'Min', ['ROLE_ADMIN']);
        $this->archiveUser($adminArchived);

        $userActive = $this->createUser('user@test.com', 'Us', 'Er', []);

        $this->em->persist($adminActive);
        $this->em->persist($adminArchived);
        $this->em->persist($userActive);
        $this->em->flush();

        self::assertSame(1, $this->repository->countActiveAdmins());
    }

    public function testFindActiveUsers(): void
    {
        $u1 = $this->createUser('a@test.com', 'Alice', 'Ze');
        $u2 = $this->createUser('b@test.com', 'Bob', 'Ay');
        $u3 = $this->createUser('c@test.com', 'Cara', 'By');
        $this->archiveUser($u3);

        $this->em->persist($u1);
        $this->em->persist($u2);
        $this->em->persist($u3);
        $this->em->flush();
        $this->em->clear();

        $active = $this->repository->findActiveUsers();

        self::assertCount(2, $active);
        self::assertSame('Ay', $active[0]->getLastName());
        self::assertSame('Ze', $active[1]->getLastName());
    }

    public function testFindForAdminListFiltersAndSorting(): void
    {
        $u1 = $this->createUser('john@test.com', 'John', 'Doe');
        $u2 = $this->createUser('alice@test.com', 'Alice', 'Smith');

        $u3 = $this->createUser('arch_smi@test.com', 'Arch', 'Ived');
        $this->archiveUser($u3);

        $this->em->persist($u1);
        $this->em->persist($u2);
        $this->em->persist($u3);
        $this->em->flush();
        $this->em->clear();

        $results = $this->repository->findForAdminList(null, 'name', 'ASC', false);
        self::assertCount(2, $results);

        $searchNoArchived = $this->repository->findForAdminList('smi', 'name', 'ASC', false);
        self::assertCount(1, $searchNoArchived);
        self::assertSame('Smith', $searchNoArchived[0]->getLastName());

        $searchWithArchived = $this->repository->findForAdminList('smi', 'name', 'ASC', true);
        self::assertCount(2, $searchWithArchived);

        $withArchived = $this->repository->findForAdminList(null, 'name', 'ASC', true);
        self::assertCount(3, $withArchived);
    }

    public function testFindForAdminListPaginated(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $u = $this->createUser("u$i@test.com", "First$i", "Last$i");
            if ($i === 5) {
                $this->archiveUser($u);
            }
            $this->em->persist($u);
        }
        $this->em->flush();
        $this->em->clear();

        $page1 = $this->repository->findForAdminListPaginated('', 'active', 'name', 'ASC', 1, 2);

        self::assertSame(4, $page1['total']);
        self::assertCount(2, $page1['items']);

        $page2 = $this->repository->findForAdminListPaginated('', 'active', 'name', 'ASC', 2, 2);
        self::assertCount(2, $page2['items']);

        $page3 = $this->repository->findForAdminListPaginated('', 'active', 'name', 'ASC', 3, 2);
        self::assertCount(0, $page3['items']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em, $this->repository);
        self::ensureKernelShutdown();
    }
}