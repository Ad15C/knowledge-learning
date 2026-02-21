<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ResetUsersCommand extends Command
{
    protected static $defaultName = 'app:reset-users';
    protected static $defaultDescription = 'Supprime tous les utilisateurs et leurs données liées (achats, paniers, commandes).';

    private EntityManagerInterface $em;
    private UserRepository $userRepository;

    public function __construct(EntityManagerInterface $em, UserRepository $userRepository)
    {
        parent::__construct();
        $this->em = $em;
        $this->userRepository = $userRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->userRepository->findAll();

        if (empty($users)) {
            $io->warning('Aucun utilisateur trouvé.');
            return Command::SUCCESS;
        }

        foreach ($users as $user) {
            $this->em->remove($user);
        }

        $this->em->flush();

        $io->success('Tous les utilisateurs et leurs données liées ont été supprimés !');

        return Command::SUCCESS;
    }
}