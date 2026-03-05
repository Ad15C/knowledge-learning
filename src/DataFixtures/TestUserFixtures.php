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

    public const USER_EMAIL = 'testuser@example.com';
    public const ADMIN_EMAIL = 'testadmin@example.com';

    public const USER_PASSWORD = 'TestPassword123!';
    public const ADMIN_PASSWORD = 'TestPassword123!';

    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // USER
        $user = (new User())
            ->setEmail(self::USER_EMAIL)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setIsVerified(true)
            ->setRoles(['ROLE_USER']);

        $user->setPassword($this->passwordHasher->hashPassword($user, self::USER_PASSWORD));
        $manager->persist($user);
        $this->addReference(self::USER_REF, $user);

        // ADMIN
        $admin = (new User())
            ->setEmail(self::ADMIN_EMAIL)
            ->setFirstName('Test')
            ->setLastName('Admin')
            ->setIsVerified(true)
            ->setRoles(['ROLE_ADMIN']);

        $admin->setPassword($this->passwordHasher->hashPassword($admin, self::ADMIN_PASSWORD));
        $manager->persist($admin);
        $this->addReference(self::ADMIN_REF, $admin);

        $manager->flush();
    }
}