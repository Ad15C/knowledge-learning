<?php

namespace App\Tests\Controller\User;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractUserWebTestCase extends WebTestCase
{
    protected EntityManagerInterface $em;
    protected KernelBrowser $client;
    protected AbstractDatabaseTool $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();

        // ✅ Charge fixtures via Liip => ReferenceRepository OK
        $this->databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();

        $this->databaseTool->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        // Optionnel (souvent pas nécessaire) :
        // $this->em->clear();
    }

    protected function getFixtureUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => TestUserFixtures::USER_EMAIL,
        ]);

        self::assertNotNull($user, 'TestUserFixtures doit créer ' . TestUserFixtures::USER_EMAIL);

        return $user;
    }
}