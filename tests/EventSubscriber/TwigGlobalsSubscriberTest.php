<?php

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TwigGlobalsSubscriberTest extends WebTestCase
{
    private function createUser(EntityManagerInterface $em): User
    {
        $user = (new User())
            ->setEmail('user@test.com')
            ->setPassword('password')
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testLoginPageUsingBaseDoesNotCrashAsVisitor(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();

        // Menu visiteur attendu (selon ton base.html.twig actuel)
        $this->assertSelectorTextContains('nav', 'Accueil');
        $this->assertSelectorTextContains('nav', 'Thèmes');
        $this->assertSelectorTextContains('nav', "S'inscrire");
        $this->assertSelectorTextContains('nav', 'Se connecter');

        // IMPORTANT: en visiteur, ton menu n'affiche pas "Panier"
        $this->assertSelectorTextNotContains('nav', 'Panier');
    }

    public function testHomepageUsingBaseShowsCartMenuWhenLoggedIn(): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Nettoyage minimal
        $em->createQuery('DELETE FROM App\Entity\User')->execute();

        $user = $this->createUser($em);
        $client->loginUser($user);

        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        // En user connecté, ton menu contient "Panier"
        $this->assertSelectorTextContains('nav', 'Panier');
    }
}