<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:delete-user',
    description: 'Supprime un utilisateur et ses données liées (selon cascades Doctrine / FK).'
)]
class DeleteTestUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $appEnv, // injecté via services.yaml
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email de l’utilisateur à supprimer')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Autoriser en production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->appEnv === 'prod' && !$input->getOption('force')) {
            $io->error("Commande bloquée en production. Utilisez --force si vous savez ce que vous faites.");
            return Command::FAILURE;
        }

        $email = $input->getArgument('email');
        if (!$email) {
            $email = $io->ask('Email de l’utilisateur à supprimer ?');
        }
        $email = (string) $email;

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $io->warning("Aucun utilisateur trouvé avec l'email : $email");
            return Command::SUCCESS;
        }

        $numPurchases = method_exists($user, 'getPurchases') ? count($user->getPurchases()) : null;
        $numCertifications = method_exists($user, 'getCertifications') ? count($user->getCertifications()) : null;
        $numLessonsValidated = method_exists($user, 'getLessonValidated') ? count($user->getLessonValidated()) : null;

        $io->section("Résumé des données de l'utilisateur : $email");
        $lines = [];
        if ($numPurchases !== null) $lines[] = "Achats : $numPurchases";
        if ($numCertifications !== null) $lines[] = "Certifications : $numCertifications";
        if ($numLessonsValidated !== null) $lines[] = "Leçons validées : $numLessonsValidated";
        if (empty($lines)) $lines[] = "(Relations non disponibles sur l'entité User)";

        $io->listing($lines);

        if (!$io->confirm("Confirmer la suppression définitive de $email ?", false)) {
            $io->warning('Suppression annulée.');
            return Command::SUCCESS;
        }

        $this->em->remove($user);
        $this->em->flush();

        $io->success("Utilisateur $email supprimé.");
        return Command::SUCCESS;
    }
}