<?php

namespace App\Tests\Command;

use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ResetUsersCommandTest extends KernelTestCase
{
    use DoctrineSchemaTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($this->em);
    }

    public function testResetsUsersWhenConfirmed(): void
    {
        $u1 = new User();
        $u1->setEmail('a@example.com')->setFirstname('A')->setLastname('A')->setRoles(['ROLE_USER'])->setPassword('hashed');
        $this->em->persist($u1);

        $u2 = new User();
        $u2->setEmail('b@example.com')->setFirstname('B')->setLastname('B')->setRoles(['ROLE_USER'])->setPassword('hashed');
        $this->em->persist($u2);

        $this->em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('app:reset-users');

        $tester = new CommandTester($command);
        $tester->setInputs(['yes']); // confirmation

        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertCount(0, $this->em->getRepository(User::class)->findAll());
        $this->assertStringContainsString('supprimés', $tester->getDisplay());
    }

    public function testDoesNotResetUsersWhenCancelled(): void
    {
        $u1 = new User();
        $u1->setEmail('a@example.com')->setFirstname('A')->setLastname('A')->setRoles(['ROLE_USER'])->setPassword('hashed');
        $this->em->persist($u1);
        $this->em->flush();

        $application = new Application(self::$kernel);
        $command = $application->find('app:reset-users');

        $tester = new CommandTester($command);
        $tester->setInputs(['no']); // annulation

        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $this->em->getRepository(User::class)->findAll());
        $this->assertStringContainsString('Suppression annulée', $tester->getDisplay());
    }

    public function testWarnsWhenNoUsers(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:reset-users');

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Aucun utilisateur trouvé', $tester->getDisplay());
    }
}