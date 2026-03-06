<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TestUserFixtures extends Fixture
{
    public const USER_REF = 'user_test';
    public const ADMIN_REF = 'admin_test';
    public const ARCHIVED_USER_REF = 'archived_user_test';

    public const USER_EMAIL = 'testuser@example.com';
    public const ADMIN_EMAIL = 'testadmin@example.com';
    public const ARCHIVED_EMAIL = 'archiveduser@example.com';

    public const USER_PASSWORD = 'TestPassword123!';
    public const ADMIN_PASSWORD = 'TestPassword123!';
    public const ARCHIVED_PASSWORD = 'TestPassword123!';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // USER ACTIF
        $user = (new User())
            ->setEmail(self::USER_EMAIL)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setIsVerified(true)
            ->setRoles(['ROLE_USER'])
            ->setCreatedAt(new \DateTimeImmutable('-10 days'));

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, self::USER_PASSWORD)
        );

        $manager->persist($user);
        $this->addReference(self::USER_REF, $user);

        // ADMIN ACTIF
        $admin = (new User())
            ->setEmail(self::ADMIN_EMAIL)
            ->setFirstName('Test')
            ->setLastName('Admin')
            ->setIsVerified(true)
            ->setRoles(['ROLE_ADMIN'])
            ->setCreatedAt(new \DateTimeImmutable('-8 days'));

        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, self::ADMIN_PASSWORD)
        );

        $manager->persist($admin);
        $this->addReference(self::ADMIN_REF, $admin);

        // USER ARCHIVÉ
        $archivedUser = (new User())
            ->setEmail(self::ARCHIVED_EMAIL)
            ->setFirstName('Archived')
            ->setLastName('User')
            ->setIsVerified(true)
            ->setRoles(['ROLE_USER'])
            ->setCreatedAt(new \DateTimeImmutable('-20 days'))
            ->setArchivedAt(new \DateTimeImmutable('-2 days'));

        $archivedUser->setPassword(
            $this->passwordHasher->hashPassword($archivedUser, self::ARCHIVED_PASSWORD)
        );

        $manager->persist($archivedUser);
        $this->addReference(self::ARCHIVED_USER_REF, $archivedUser);

        $manager->flush();
    }
}