<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TestUserFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Addie');
        $user->setLastName('C');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('plain_password'); // hash via UserPasswordHasherInterface en vrai
        $manager->persist($user);

        $manager->flush();
    }
}