<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TestUserFixtures extends Fixture
{
    public const USER_REF = 'user_test';

    public const USER_EMAIL = 'testuser@example.com';
    public const USER_PASSWORD = 'TestPassword123!';

    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Avec Liip/TestFixturesBundle, la DB est reset => pas besoin de supprimer un user existant.
        // Si tu exécutes aussi ces fixtures en dev, tu peux laisser ce bloc,
        // mais pour les tests c'est généralement inutile.

        $user = new User();
        $user->setEmail(self::USER_EMAIL)
            ->setFirstName('Addie')
            ->setLastName('C')
            ->setRoles(['ROLE_USER']);

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, self::USER_PASSWORD)
        );

        $manager->persist($user);

        // Référence AVANT flush (bonne pratique)
        $this->addReference(self::USER_REF, $user);

        $manager->flush();
    }
}