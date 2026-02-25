<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationControllerTest extends WebTestCase
{
    use DoctrineSchemaTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        // Base propre et complète à chaque test (plus robuste)
        $this->resetDatabaseSchema($this->em);
    }

    public function testRegistrationPageIsAccessible(): void
    {
        $this->client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="registration_form"]');
        $this->assertSelectorTextContains('h1', 'Inscription');
    }

    public function testValidRegistrationCreatesUserWithVerificationToken(): void
    {
        $crawler = $this->client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[email]' => 'testuser@example.com',
            'registration_form[plainPassword][first]' => 'Test@1234',
            'registration_form[plainPassword][second]' => 'Test@1234',
        ]);

        $this->client->submit($form);

        // Redirect after successful registration
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Flash success (dans base.html.twig: .flash.flash-success)
        $this->assertSelectorExists('.flash-success, .flash.flash-success');
        $this->assertSelectorTextContains('.flash-success, .flash.flash-success', 'Inscription réussie');

        // User created
        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'testuser@example.com']);
        $this->assertNotNull($user);

        // Password hashed and valid
        $this->assertTrue($this->passwordHasher->isPasswordValid($user, 'Test@1234'));

        // Role default
        $this->assertContains('ROLE_USER', $user->getRoles());

        // NEW: verification requirements
        $this->assertFalse($user->isVerified());
        $this->assertNotEmpty($user->getVerificationToken());
        $this->assertNotNull($user->getVerificationTokenExpiresAt());

        // Optional: in test env, we should see the "Lien de vérification (dev)" flash
        // (On ne teste pas l'URL exacte, juste la présence du message)
        $this->assertSelectorExists('.flash-info, .flash.flash-info');
        $this->assertSelectorTextContains('.flash-info, .flash.flash-info', 'Lien de vérification');
    }

    public function testDuplicateEmailRegistrationFails(): void
    {
        // Existing user
        $existingUser = new User();
        $existingUser->setFirstName('Exist');
        $existingUser->setLastName('User');
        $existingUser->setEmail('duplicate@example.com');
        $existingUser->setRoles(['ROLE_USER']);
        $existingUser->setIsVerified(true);

        $hashedPassword = $this->passwordHasher->hashPassword($existingUser, 'Test@1234');
        $existingUser->setPassword($hashedPassword);

        $this->em->persist($existingUser);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'User',
            'registration_form[email]' => 'duplicate@example.com',
            'registration_form[plainPassword][first]' => 'Test@1234',
            'registration_form[plainPassword][second]' => 'Test@1234',
        ]);

        $this->client->submit($form);

        // stays on page (no redirect)
        $this->assertResponseStatusCodeSame(200);

        // validation error
        $this->assertSelectorTextContains('.form-error', 'Cet email est déjà utilisé.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}