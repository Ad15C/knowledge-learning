<?php

namespace App\Tests\Command;

use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateAdminCommandTest extends KernelTestCase
{
    use DoctrineSchemaTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($this->em);
    }

    public function testCreatesAdminUser(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-admin');

        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'email' => 'admin@example.com',
            '--password' => 'AdminPassword123!',
        ]);

        $this->assertSame(0, $exitCode);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('Admin', $output);
        $this->assertStringContainsString('admin@example.com', $output);

        $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        $this->assertNotNull($admin);

        $this->assertContains('ROLE_ADMIN', $admin->getRoles());
        $this->assertNotEmpty($admin->getPassword());
    }

    public function testUpdatesExistingAdminUser(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:create-admin');
        $tester = new CommandTester($command);

        // 1) création
        $tester->execute([
            'email' => 'admin@example.com',
            '--password' => 'AdminPassword123!',
            '--firstname' => 'Admin',
            '--lastname' => 'User',
        ]);

        // 2) mise à jour
        $tester2 = new CommandTester($command);
        $exitCode = $tester2->execute([
            'email' => 'admin@example.com',
            '--password' => 'AdminPassword123!',
            '--firstname' => 'Super',
            '--lastname' => 'Admin',
        ]);

        $this->assertSame(0, $exitCode);

        $output = $tester2->getDisplay();
        $this->assertStringContainsString('mis à jour', $output);

        /** @var User $admin */
        $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        $this->assertNotNull($admin);
        $this->assertSame('Super', $admin->getFirstname());
        $this->assertSame('Admin', $admin->getLastname());
        $this->assertContains('ROLE_ADMIN', $admin->getRoles());
    }
}