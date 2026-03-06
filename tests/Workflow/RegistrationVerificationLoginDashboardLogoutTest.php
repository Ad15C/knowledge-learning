<?php

namespace App\Tests\Workflow;

use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegistrationVerificationLoginDashboardLogoutTest extends WebTestCase
{
    use DoctrineSchemaTrait;

    private EntityManagerInterface $em;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($this->em);
    }

    public function testWorkflowRegistrationToLogout(): void
    {
        // 1) Register
        $crawler = $this->client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[email]' => 'test@example.com',
            'registration_form[plainPassword][first]' => 'Test1234Secure!',
            'registration_form[plainPassword][second]' => 'Test1234Secure!',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/login');

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => 'test@example.com',
        ]);

        $this->assertNotNull($user);
        $this->assertFalse($user->isVerified());
        $this->assertNotEmpty($user->getVerificationToken());

        // 2) Login BEFORE verification => accès refusé
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'test@example.com',
            '_password' => 'Test1234Secure!',
        ]);

        $this->client->submit($loginForm);

        if ($this->client->getResponse()->isRedirection()) {
            $crawler = $this->client->followRedirect();
        } else {
            $crawler = $this->client->getCrawler();
        }

        $this->assertResponseIsSuccessful();

        // On ne doit pas être sur le dashboard
        $this->assertNotSame('/dashboard', $this->client->getRequest()->getPathInfo());

        // Une erreur de connexion doit être visible
        $this->assertSelectorExists('.flash-error');

        // 3) Verify email with token
        $token = $user->getVerificationToken();
        $this->assertNotNull($token);

        $this->client->request('GET', '/verify-email?token=' . urlencode($token));
        $this->assertResponseRedirects('/login');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->em->clear();

        /** @var User|null $verifiedUser */
        $verifiedUser = $this->em->getRepository(User::class)->findOneBy([
            'email' => 'test@example.com',
        ]);

        $this->assertNotNull($verifiedUser);
        $this->assertTrue($verifiedUser->isVerified());
        $this->assertNull($verifiedUser->getVerificationToken());

        // 4) Login AFTER verification => succès
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'test@example.com',
            '_password' => 'Test1234Secure!',
        ]);

        $this->client->submit($loginForm);
        $this->assertTrue(
            $this->client->getResponse()->isRedirection(),
            'Après vérification, le login doit rediriger.'
        );

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Vérifications robustes du dashboard
        $this->assertSame('/dashboard', $this->client->getRequest()->getPathInfo());
        $this->assertSelectorExists('.dashboard-layout');
        $this->assertLinkExistsWithText($crawler, 'Mes achats');
        $this->assertLinkExistsWithText($crawler, 'Mes certificats');
        $this->assertLinkExistsWithText($crawler, 'Sécurité');

        // 5) Logout
        $this->client->request('GET', '/logout');
        $this->assertTrue(
            $this->client->getResponse()->isRedirection(),
            'La route /logout doit rediriger.'
        );

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Selon ta config firewall, la redirection peut être vers / ou /login
        // On vérifie surtout que l'utilisateur n'est plus authentifié
        // et qu'on retrouve un menu visiteur ou un formulaire
        $this->assertTrue(
            $crawler->filter('form')->count() > 0
            || $crawler->filterXPath('//a[contains(normalize-space(.), "Se connecter")]')->count() > 0
        );

        // 6) Dashboard after logout => accès refusé / redirection login
        $this->client->request('GET', '/dashboard');
        $this->assertTrue(
            $this->client->getResponse()->isRedirection(),
            'Après logout, /dashboard doit rediriger.'
        );

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    private function assertLinkExistsWithText($crawler, string $text): void
    {
        $this->assertGreaterThan(
            0,
            $crawler->filterXPath(sprintf('//a[contains(normalize-space(.), "%s")]', $text))->count(),
            sprintf('Lien "%s" introuvable', $text)
        );
    }
}