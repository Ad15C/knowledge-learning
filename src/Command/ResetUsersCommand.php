<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ResetUsersCommand extends Command
{
    protected static $defaultName = 'app:reset-test-users';
    protected static $defaultDescription = 'Supprime les utilisateurs de test et en recrée un nouveau.';

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

        // Récupérer tous les utilisateurs de test
        $usersToDelete = $this->em->getRepository(User::class)
            ->findBy(['email' => 'test@example.com']); // tu peux aussi filtrer par rôle si tu veux

        foreach ($usersToDelete as $user) {
            $this->em->remove($user);
            $io->writeln("Utilisateur {$user->getEmail()} supprimé.");
        }

        $this->em->flush();
        $io->success(count($usersToDelete) . " utilisateur(s) de test supprimé(s).");

        // Créer un nouvel utilisateur de test
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstname('Test');
        $user->setLastname('User');
        $user->setRoles(['ROLE_USER']);

        // Mot de passe sécurisé (respecte tes contraintes : min 8, majuscule, chiffre)
        $plainPassword = 'Test1234Secure!'; 
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();

        $io->success("Nouvel utilisateur de test créé : test@example.com / $plainPassword");

        return Command::SUCCESS;
    }
}
