<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;


class TestUserFixtures extends Fixture
{
    public const USER_EMAIL = 'testuser@example.com';
    public const USER_PASSWORD = 'TestPassword123!';

    public function load(ObjectManager $manager): void
    {
        // Vérifie si l'utilisateur existe déjà pour éviter UniqueConstraintViolation
        $existingUser = $manager->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);
        if ($existingUser) {
            $manager->remove($existingUser);
            $manager->flush();
        }

        $user = new User();
        $user->setEmail(self::USER_EMAIL);
        $user->setFirstName('Addie');
        $user->setLastName('C');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('plain_password'); 

        $manager->persist($user);
        $manager->flush();
    }
}