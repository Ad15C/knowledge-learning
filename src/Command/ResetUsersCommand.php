<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reset-users',
    description: 'Supprime tous les utilisateurs (et leurs données liées selon cascades Doctrine / FK).'
)]
class ResetUsersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly string $appEnv, // injecté via services.yaml
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Autoriser en production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->appEnv === 'prod' && !$input->getOption('force')) {
            $io->error("Commande bloquée en production. Utilisez --force si vous savez ce que vous faites.");
            return Command::FAILURE;
        }

        $users = $this->userRepository->findAll();

        if (empty($users)) {
            $io->warning('Aucun utilisateur trouvé.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('Vous allez supprimer %d utilisateur(s).', count($users)));

        if (!$io->confirm('Confirmer la suppression de TOUS les utilisateurs ?', false)) {
            $io->warning('Suppression annulée.');
            return Command::SUCCESS;
        }

        foreach ($users as $user) {
            $this->em->remove($user);
        }

        $this->em->flush();

        $io->success('Tous les utilisateurs ont été supprimés.');
        return Command::SUCCESS;
    }
}