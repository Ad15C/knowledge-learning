<?php

namespace App\Tests\Command;

use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DeleteTestUserCommandTest extends KernelTestCase
{
    use DoctrineSchemaTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($this->em);
    }

    public function testDeletesUserWhenConfirmed(): void
    {
        $user = new User();
        $user->setEmail('to-delete@example.com');
        $user->setFirstname('To');
        $user->setLastname('Delete');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('hashed');

        $this->em->persist($user);
        $this->em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('app:delete-user');

        $tester = new CommandTester($command);

        // Ici: email en argument => plus de question sur l'email
        // Il reste la confirmation => on fournit "yes"
        $tester->setInputs(['yes']);

        $exitCode = $tester->execute([
            'email' => 'to-delete@example.com',
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertNull(
            $this->em->getRepository(User::class)->findOneBy(['email' => 'to-delete@example.com'])
        );

        $this->assertStringContainsString('supprimé', $tester->getDisplay());
    }

    public function testDoesNotDeleteUserWhenCancelled(): void
    {
        $user = new User();
        $user->setEmail('keep@example.com');
        $user->setFirstname('Keep');
        $user->setLastname('Me');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('hashed');

        $this->em->persist($user);
        $this->em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('app:delete-user');

        $tester = new CommandTester($command);
        $tester->setInputs(['no']);

        $exitCode = $tester->execute([
            'email' => 'keep@example.com',
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertNotNull(
            $this->em->getRepository(User::class)->findOneBy(['email' => 'keep@example.com'])
        );

        $this->assertStringContainsString('Suppression annulée', $tester->getDisplay());
    }

    public function testWarnsWhenUserNotFound(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:delete-user');

        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'email' => 'missing@example.com',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Aucun utilisateur trouvé', $tester->getDisplay());
    }
}