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

        // Liens “publics”
        $this->assertLinkExistsWithText($crawler, 'Accueil');
        $this->assertLinkExistsWithText($crawler, 'Thèmes');
        $this->assertLinkExistsWithText($crawler, 'Panier');
        $this->assertLinkExistsWithText($crawler, 'Contact');

        // Anonyme => Connexion + Inscription
        $this->assertLinkExistsWithText($crawler, 'Connexion');
        $this->assertLinkExistsWithText($crawler, 'Inscription');

        // Anonyme => PAS de Déconnexion / Dashboard
        $this->assertLinkNotExistsWithText($crawler, 'Déconnexion');
        $this->assertLinkNotExistsWithText($crawler, 'Dashboard');
    }

    public function testMenuLoggedUserAndNavigationLinks(): void
    {
        $this->createVerifiedUser('menuuser@example.com', 'MenuPass123!');

        // Login
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'menuuser@example.com',
            '_password' => 'MenuPass123!',
        ]);
        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // On va sur homepage pour tester le menu global
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Publics toujours présents
        $this->assertLinkExistsWithText($crawler, 'Accueil');
        $this->assertLinkExistsWithText($crawler, 'Thèmes');
        $this->assertLinkExistsWithText($crawler, 'Panier');
        $this->assertLinkExistsWithText($crawler, 'Contact');

        // Connecté => Dashboard + Déconnexion
        $this->assertLinkExistsWithText($crawler, 'Dashboard');
        $this->assertLinkExistsWithText($crawler, 'Déconnexion');

        // Connecté => plus Connexion/Inscription
        $this->assertLinkNotExistsWithText($crawler, 'Connexion');
        $this->assertLinkNotExistsWithText($crawler, 'Inscription');

        // Navigation “Dashboard” (tes sous-liens existent dans le menuItems)
        // On teste les routes directement (plus fiable que de cliquer un submenu en JS)
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

        // Logout via menu route
        $this->client->request('GET', '/logout');
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Après logout => dashboard redirige vers login
        $this->client->request('GET', '/dashboard');
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    private function createVerifiedUser(string $email, string $plainPassword): void
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