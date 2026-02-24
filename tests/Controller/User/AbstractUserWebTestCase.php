<?php

namespace App\Tests\Controller\User;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\User;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractUserWebTestCase extends WebTestCase
{
    protected EntityManagerInterface $em;
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient(); // 
        $this->em = static::getContainer()->get('doctrine')->getManager();

        // Schéma fresh (SQLite test)
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        // Purge
        (new ORMPurger($this->em))->purge();

        // Charge fixtures via container
        static::getContainer()->get(TestUserFixtures::class)->load($this->em);
        static::getContainer()->get(ThemeFixtures::class)->load($this->em);

        $this->em->clear();
    }

    protected function getFixtureUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);
        self::assertNotNull($user, 'TestUserFixtures doit créer ' . TestUserFixtures::USER_EMAIL);
        return $user;
    }
}