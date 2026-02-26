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

        // Visiteur (selon ton base.html.twig actuel)
        $this->assertLinkExistsWithText($crawler, 'Accueil');
        $this->assertLinkExistsWithText($crawler, 'Thèmes');

        // Visiteur => PAS de Panier / Contact
        $this->assertLinkNotExistsWithText($crawler, 'Panier');
        $this->assertLinkNotExistsWithText($crawler, 'Contact');

        // Visiteur => liens auth
        $this->assertLinkExistsWithText($crawler, "S'inscrire");
        $this->assertLinkExistsWithText($crawler, 'Se connecter');

        // Visiteur => PAS de Déconnexion / Dashboard
        $this->assertLinkNotExistsWithText($crawler, 'Déconnexion');
        $this->assertLinkNotExistsWithText($crawler, 'Dashboard User');
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

        // Homepage pour tester le menu global
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Liens présents pour un user connecté (selon ton base.html.twig)
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

        // Adapté à tes setters utilisés ailleurs
        $user->setFirstname('Menu');
        $user->setLastname('User');

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