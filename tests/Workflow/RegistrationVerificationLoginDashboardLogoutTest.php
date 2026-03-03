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
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($user);
        $this->assertFalse($user->isVerified());
        $this->assertNotEmpty($user->getVerificationToken());

        // 2) Login BEFORE verification => should NOT reach dashboard
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'test@example.com',
            '_password' => 'Test1234Secure!',
        ]);

        $this->client->submit($loginForm);

        // Certaines configs redirigent (ex: back to /login)
        if ($this->client->getResponse()->isRedirection()) {
            $this->client->followRedirect();
        }
        $this->assertResponseIsSuccessful();

        // On vérifie qu'on n'est PAS sur le dashboard
        $this->assertStringNotContainsString('/dashboard', (string) $this->client->getRequest()->getPathInfo());

        // Et qu'une erreur est affichée (message/flash)
        // Ton template login affiche: <div class="flash flash-error">...</div> si error
        $this->assertSelectorExists('.flash-error, .flash.flash-error');

        // 3) Verify email using token
        $this->client->request('GET', '/verify-email?token=' . urlencode($user->getVerificationToken()));
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->em->clear();

        /** @var User|null $verifiedUser */
        $verifiedUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($verifiedUser);
        $this->assertTrue($verifiedUser->isVerified());
        $this->assertNull($verifiedUser->getVerificationToken());

        // 4) Login AFTER verification => should succeed and reach dashboard
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'test@example.com',
            '_password' => 'Test1234Secure!',
        ]);

        $this->client->submit($loginForm);
        $this->assertTrue($this->client->getResponse()->isRedirection());

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Dashboard content check (stable)
        $this->assertSelectorExists('h1');
        $this->assertSelectorTextContains('h1', 'Bonjour,');

        // 5) Logout => redirect login
        $this->client->request('GET', '/logout');
        $this->assertTrue($this->client->getResponse()->isRedirection());

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form'); // page login a un form

        // 6) Dashboard after logout => should redirect to login
        $this->client->request('GET', '/dashboard');
        $this->assertTrue($this->client->getResponse()->isRedirection());

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }
}