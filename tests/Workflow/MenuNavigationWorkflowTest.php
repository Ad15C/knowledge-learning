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

        // Visiteur
        $this->assertLinkExistsWithText($crawler, 'Accueil');
        $this->assertLinkExistsWithText($crawler, 'Thèmes');

        // Visiteur => PAS de Panier / Contact / Dashboard / Déconnexion
        $this->assertLinkNotExistsWithText($crawler, 'Panier');
        $this->assertLinkNotExistsWithText($crawler, 'Contact');
        $this->assertLinkNotExistsWithText($crawler, 'Déconnexion');
        $this->assertLinkNotExistsWithText($crawler, 'Dashboard User');
        $this->assertLinkNotExistsWithText($crawler, 'Dashboard Admin');

        // Visiteur => liens auth
        $this->assertLinkExistsWithText($crawler, "S'inscrire");
        $this->assertLinkExistsWithText($crawler, 'Se connecter');
    }

    public function testMenuLoggedUserAndNavigationLinks(): void
    {
        $user = $this->createVerifiedUser('menuuser@example.com', 'MenuPass123!');

        // Login robuste (ne dépend pas du formulaire)
        $this->client->loginUser($user);

        // Homepage pour tester le menu global
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Liens présents pour un user connecté (base.html.twig)
        $this->assertLinkExistsWithText($crawler, 'Accueil');
        $this->assertLinkExistsWithText($crawler, 'Thèmes');
        $this->assertLinkExistsWithText($crawler, 'Panier');
        $this->assertLinkExistsWithText($crawler, 'Contact');

        // Dashboard + Déconnexion
        $this->assertLinkExistsWithText($crawler, 'Dashboard User');
        $this->assertLinkExistsWithText($crawler, 'Déconnexion');

        // User connecté => plus Se connecter / S'inscrire
        $this->assertLinkNotExistsWithText($crawler, 'Se connecter');
        $this->assertLinkNotExistsWithText($crawler, "S'inscrire");

        // Navigation “Dashboard” (routes directes)
        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/edit');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/password');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/purchases');
        $this->assertResponseIsSuccessful();

        $this->client->request('GET', '/dashboard/certifications');
        $this->assertResponseIsSuccessful();

        // Logout
        $this->client->request('GET', '/logout');

        // Selon config firewall, logout redirige vers /, /login, etc.
        $this->assertTrue(
            $this->client->getResponse()->isRedirection(),
            'La route /logout doit rediriger.'
        );
        $this->client->followRedirect();

        // Après logout => /dashboard redirige vers login
        $this->client->request('GET', '/dashboard');
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // On s'attend à tomber sur une page avec un formulaire de login
        $this->assertSelectorExists('form');
    }

    private function createVerifiedUser(string $email, string $plainPassword): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);

        // Adapté à tes setters (tu utilises firstname/lastname ailleurs)
        $user->setFirstname('Menu');
        $user->setLastname('User');

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