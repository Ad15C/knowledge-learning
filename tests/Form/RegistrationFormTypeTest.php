<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Repository\UserRepository;
use App\Entity\User;

class RegistrationFormTypeTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->entityManager = $this->client->getContainer()
            ->get('doctrine')
            ->getManager();

        // Nettoyer la table User avant chaque test
        $this->entityManager->createQuery('DELETE FROM App\Entity\User u')->execute();
    }

    public function testRegistrationWithValidData(): void
    {
        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('S\'inscrire')->form([
            'registration_form[firstname]' => 'John',
            'registration_form[lastname]' => 'Doe',
            'registration_form[email]' => 'john.doe@example.com',
            'registration_form[plainPassword][first]' => 'Password123!',
            'registration_form[plainPassword][second]' => 'Password123!',
        ]);

        $this->client->submit($form);

        // Vérifie la redirection après succès
        $this->assertResponseRedirects('/login');

        // Vérifie que l'utilisateur a bien été créé
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'john.doe@example.com']);
        $this->assertNotNull($user);
        $this->assertSame('John', $user->getFirstname());
        $this->assertSame('Doe', $user->getLastname());
    }

    public function testDuplicateEmailFails(): void
    {
        // Crée déjà un utilisateur
        $user = new User();
        $user->setFirstname('Jane')->setLastname('Doe')->setEmail('jane@example.com')->setPassword('Password123!');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/register');

        $form = $crawler->selectButton('S\'inscrire')->form([
            'registration_form[firstname]' => 'John',
            'registration_form[lastname]' => 'Doe',
            'registration_form[email]' => 'jane@example.com',
            'registration_form[plainPassword][first]' => 'Password123!',
            'registration_form[plainPassword][second]' => 'Password123!',
        ]);

        $crawler = $this->client->submit($form);

        // Vérifie que le formulaire est de nouveau affiché
        $this->assertResponseStatusCodeSame(200);

        // Vérifie que le message d'erreur UniqueEntity est affiché
        $this->assertSelectorTextContains('.form-error', 'Cet email est déjà utilisé.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null; // évite les fuites de mémoire
    }
}