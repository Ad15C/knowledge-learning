<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationControllerTest extends WebTestCase
{
    private $client;
    private $em;
    private $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        // Nettoyer la table User avant chaque test
        $users = $this->em->getRepository(User::class)->findAll();
        foreach ($users as $user) {
            $this->em->remove($user);
        }
        $this->em->flush();
    }

    public function testRegistrationPageIsAccessible(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="registration_form"]');
        $this->assertSelectorTextContains('h1', 'Inscription');
    }

    public function testValidRegistrationCreatesUser(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('S\'inscrire')->form();
        $form['registration_form[firstName]'] = 'Test';
        $form['registration_form[lastName]'] = 'User';
        $form['registration_form[email]'] = 'testuser@example.com';
        $form['registration_form[plainPassword][first]'] = 'Test@1234';
        $form['registration_form[plainPassword][second]'] = 'Test@1234';

        $this->client->submit($form);

        // Redirection après inscription réussie
        $this->assertResponseRedirects('/login');

        $this->client->followRedirect();

        $this->assertSelectorTextContains('.flash-success', 'Inscription réussie');

        // Vérifier que l'utilisateur a bien été créé
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'testuser@example.com']);
        $this->assertNotNull($user);

        // Vérifier que le mot de passe est hashé
        $this->assertTrue($this->passwordHasher->isPasswordValid($user, 'Test@1234'));

        // Vérifier que l'utilisateur a le rôle par défaut
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testDuplicateEmailRegistrationFails(): void
    {
        // Créer un utilisateur existant avec mot de passe hashé
        $existingUser = new User();
        $existingUser->setFirstName('Exist');
        $existingUser->setLastName('User');
        $existingUser->setEmail('duplicate@example.com');
        $hashedPassword = $this->passwordHasher->hashPassword($existingUser, 'Test@1234');
        $existingUser->setPassword($hashedPassword);
        $existingUser->setRoles(['ROLE_USER']);
        $existingUser->setIsVerified(true);

        $this->em->persist($existingUser);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->selectButton('S\'inscrire')->form();

        $form['registration_form[firstName]'] = 'Test';
        $form['registration_form[lastName]'] = 'User';
        $form['registration_form[email]'] = 'duplicate@example.com';
        $form['registration_form[plainPassword][first]'] = 'Test@1234';
        $form['registration_form[plainPassword][second]'] = 'Test@1234';

        $this->client->submit($form);

        // La page reste accessible (pas de redirection)
        $this->assertResponseStatusCodeSame(200);

        // Le message d'erreur de validation est affiché
        $this->assertSelectorTextContains(
            '.form-error',
            'Cet email est déjà utilisé.'
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        $this->em = null; // éviter les fuites de mémoire
    }
}