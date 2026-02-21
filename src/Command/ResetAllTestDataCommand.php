<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Purchase;
use App\Entity\Cart;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetAllTestDataCommand extends Command
{
    protected static $defaultName = 'app:reset-all-test-data';
    protected static $defaultDescription = 'Supprime toutes les données de test et recrée un utilisateur test.';

    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Suppression des données de test');

        // Supprimer toutes les commandes / achats
        $purchases = $this->em->getRepository(Purchase::class)->findAll();
        foreach ($purchases as $purchase) {
            $this->em->remove($purchase);
        }

        $carts = $this->em->getRepository(Cart::class)->findAll();
        foreach ($carts as $cart) {
            $this->em->remove($cart);
        }

        // Supprimer tous les utilisateurs
        $users = $this->em->getRepository(User::class)->findAll();
        foreach ($users as $user) {
            $this->em->remove($user);
        }

        $this->em->flush();
        $io->success('Toutes les données de test ont été supprimées.');

        // Créer un utilisateur de test
        $io->section('Création d’un utilisateur de test');
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstname('Test');
        $user->setLastname('User');
        $user->setRoles(['ROLE_USER']);

        $plainPassword = 'Test1234Secure!';
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->em->persist($user);
        $this->em->flush();

        $io->success("Nouvel utilisateur créé : test@example.com / $plainPassword");

        return Command::SUCCESS;
    }
}