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

        // Un SEUL client pour tout le test
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

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($user);
        $this->assertFalse($user->isVerified());
        $this->assertNotEmpty($user->getVerificationToken());

        // 2) Login BEFORE verification => should fail
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'test@example.com',
            '_password' => 'Test1234Secure!',
        ]);

        $this->client->submit($loginForm);
        if ($this->client->getResponse()->isRedirection()) {
            $this->client->followRedirect();
        }

        $this->assertSelectorExists('.flash-error, .flash.flash-error');
        $this->assertSelectorTextContains('.flash-error, .flash.flash-error', 'vérifié');

        // 3) Verify email using token
        $this->client->request('GET', '/verify-email?token=' . urlencode($user->getVerificationToken()));
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();

        $this->em->clear();
        /** @var User|null $verifiedUser */
        $verifiedUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $this->assertNotNull($verifiedUser);
        $this->assertTrue($verifiedUser->isVerified());
        $this->assertNull($verifiedUser->getVerificationToken());

        // 4) Login AFTER verification => should succeed and go to dashboard
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'test@example.com',
            '_password' => 'Test1234Secure!',
        ]);

        $this->client->submit($loginForm);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();

        // 5) Dashboard accessible
        $this->assertResponseIsSuccessful();

        // 6) Logout => redirect login
        $this->client->request('GET', '/logout');
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();

        // 7) Dashboard after logout => should redirect to login
        $this->client->request('GET', '/dashboard');
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }
}