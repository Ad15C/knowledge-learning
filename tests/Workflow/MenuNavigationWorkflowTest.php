<?php

namespace App\Tests\Workflow;

use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MenuNavigationWorkflowTest extends WebTestCase
{
    use DoctrineSchemaTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($this->em);
    }

    public function testMenuAnonymous(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Menu visiteur
        $this->assertLinkExistsWithText($crawler, 'Accueil');
        $this->assertLinkExistsWithText($crawler, 'Thèmes');
        $this->assertLinkExistsWithText($crawler, "S'inscrire");
        $this->assertLinkExistsWithText($crawler, 'Se connecter');

        // Éléments absents pour un visiteur
        $this->assertLinkNotExistsWithText($crawler, 'Panier');
        $this->assertLinkNotExistsWithText($crawler, 'Contact');
        $this->assertLinkNotExistsWithText($crawler, 'Déconnexion');
        $this->assertLinkNotExistsWithText($crawler, 'Dashboard User');
        $this->assertLinkNotExistsWithText($crawler, 'Dashboard Admin');
    }

    public function testMenuLoggedUserAndNavigationLinks(): void
    {
        $user = $this->createVerifiedUser('menuuser@example.com', 'MenuPass123!');

        // Authentification sans dépendre du formulaire
        $this->client->loginUser($user);

        // 1) Test du menu principal sur la home
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        $this->assertLinkExistsWithText($crawler, 'Accueil');
        $this->assertLinkExistsWithText($crawler, 'Thèmes');
        $this->assertLinkExistsWithText($crawler, 'Panier');
        $this->assertLinkExistsWithText($crawler, 'Contact');
        $this->assertLinkExistsWithText($crawler, 'Dashboard User');
        $this->assertLinkExistsWithText($crawler, 'Déconnexion');

        $this->assertLinkNotExistsWithText($crawler, 'Se connecter');
        $this->assertLinkNotExistsWithText($crawler, "S'inscrire");
        $this->assertLinkNotExistsWithText($crawler, 'Dashboard Admin');

        // 2) Test de la page dashboard + sidebar utilisateur
        $crawler = $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $this->assertLinkExistsWithText($crawler, 'Vue d’ensemble');
        $this->assertLinkExistsWithText($crawler, 'Mon profil');
        $this->assertLinkExistsWithText($crawler, 'Sécurité');
        $this->assertLinkExistsWithText($crawler, 'Mes achats');
        $this->assertLinkExistsWithText($crawler, 'Mes certificats');

        // 3) Vérification d’accès aux routes dashboard
        $this->client->request('GET', '/dashboard/edit');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/password');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/purchases');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/certifications');
        $this->assertResponseIsSuccessful();

        // 4) Logout
        $this->client->request('GET', '/logout');
        $this->assertTrue(
            $this->client->getResponse()->isRedirection(),
            'La route /logout doit rediriger.'
        );

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // 5) Après logout, /dashboard ne doit plus être accessible
        $this->client->request('GET', '/dashboard');
        $this->assertTrue(
            $this->client->getResponse()->isRedirection(),
            'Après logout, /dashboard doit rediriger.'
        );

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // On vérifie qu’on arrive sur une page de connexion
        $this->assertSelectorExists('form');
    }

    private function createVerifiedUser(string $email, string $plainPassword): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Menu');
        $user->setLastName('User');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function assertLinkExistsWithText($crawler, string $text): void
    {
        $this->assertGreaterThan(
            0,
            $crawler->filterXPath(sprintf('//a[contains(normalize-space(.), "%s")]', $text))->count(),
            sprintf('Lien "%s" introuvable', $text)
        );
    }

    private function assertLinkNotExistsWithText($crawler, string $text): void
    {
        $this->assertSame(
            0,
            $crawler->filterXPath(sprintf('//a[contains(normalize-space(.), "%s")]', $text))->count(),
            sprintf('Lien "%s" ne devrait pas exister', $text)
        );
    }
}