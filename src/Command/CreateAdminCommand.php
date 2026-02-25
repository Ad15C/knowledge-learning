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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée (ou met à jour) un compte admin pour le back-office.'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly string $appEnv, // injecté via services.yaml
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email de l’admin', 'admin@example.com')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe admin (sinon question interactive)')
            ->addOption('firstname', null, InputOption::VALUE_REQUIRED, 'Prénom', 'Admin')
            ->addOption('lastname', null, InputOption::VALUE_REQUIRED, 'Nom', 'User')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Autoriser en production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->appEnv === 'prod' && !$input->getOption('force')) {
            $io->error("Commande bloquée en production. Utilisez --force si vous savez ce que vous faites.");
            return Command::FAILURE;
        }

        $email = (string) $input->getArgument('email');
        $password = $input->getOption('password');

        if (!$password) {
            $password = $io->askHidden('Mot de passe admin (non affiché)', function ($value) {
                if (!is_string($value) || strlen($value) < 12) {
                    throw new \RuntimeException('Le mot de passe doit faire au moins 12 caractères.');
                }
                return $value;
            });
        }

        $firstname = (string) $input->getOption('firstname');
        $lastname  = (string) $input->getOption('lastname');

        /** @var User|null $admin */
        $admin = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        $isNew = false;
        if (!$admin) {
            $admin = new User();
            $admin->setEmail($email);
            $isNew = true;
        }

        $admin->setFirstname($firstname);
        $admin->setLastname($lastname);
        $admin->setIsVerified(true);

        // on garantit ROLE_ADMIN + éventuellement ROLE_USER selon ton projet
        $roles = $admin->getRoles();
        if (!in_array('ROLE_ADMIN', $roles, true)) {
            $roles[] = 'ROLE_ADMIN';
        }
        $admin->setRoles(array_values(array_unique($roles)));

        $admin->setPassword($this->passwordHasher->hashPassword($admin, (string) $password));

        if ($isNew) {
            $this->em->persist($admin);
        }
        $this->em->flush();

        $io->success(sprintf(
            $isNew ? 'Admin créé : %s' : 'Admin mis à jour : %s',
            $admin->getEmail()
        ));

        return Command::SUCCESS;
    }
}