<?php
// src/Command/DeleteTestUserCommand.php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteTestUserCommand extends Command
{
    protected static $defaultName = 'app:delete-user';
    protected static $defaultDescription = 'Supprime un utilisateur et toutes ses données liées (achats, items, certifications, lessonValidated)';

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Demander l'email de l'utilisateur à supprimer
        $email = $io->ask('Email de l’utilisateur à supprimer ?');

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $io->warning("Aucun utilisateur trouvé avec l'email : $email");
            return Command::SUCCESS;
        }

        // Afficher un résumé
        $numPurchases = count($user->getPurchases());
        $numCertifications = count($user->getCertifications());
        $numLessonsValidated = count($user->getLessonValidated());

        $io->section("Résumé des données de l'utilisateur : $email");
        $io->listing([
            "Achats : $numPurchases",
            "Certifications : $numCertifications",
            "Leçons validées : $numLessonsValidated",
        ]);

        // Confirmation avant suppression
        if (!$io->confirm('Voulez-vous vraiment supprimer cet utilisateur et toutes ses données ?', false)) {
            $io->warning('Suppression annulée.');
            return Command::SUCCESS;
        }

        // Suppression via Doctrine
        $this->em->remove($user);
        $this->em->flush();

        $io->success("Utilisateur $email et toutes ses données liées ont été supprimés.");
        return Command::SUCCESS;
    }
}