<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class UserProfileFormTypeTest extends WebTestCase
{
    private function createUserClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return static::createClient([], [
            'HTTP_ACCEPT_LANGUAGE' => 'fr', // par défaut français
        ]);
    }

    private function createAndPersistUser(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $email = 'testuser@example.com'
    ): User {
        $container = $client->getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email)
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setPassword(
                $passwordHasher->hashPassword($user, 'Password1!')
            )
            ->setIsVerified(true);

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function assertFormErrorContains(Crawler $crawler, string $expectedText): void
    {
        $errors = $crawler->filter('.form-error')->each(fn($node) => $node->text());
        $found = false;
        foreach ($errors as $error) {
            if (str_contains($error, $expectedText)) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Expected form error to contain: '$expectedText'. Actual errors: " . implode(', ', $errors));
    }

    public function testEditProfilePageDisplaysCorrectly(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="editProfileForm"]');
    }

    public function testSubmitValidProfileForm(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/edit');

        $form = $crawler->selectButton('Mettre à jour')->form([
            'editProfileForm[firstName]' => 'Alice',
            'editProfileForm[lastName]'  => 'Smith',
            'editProfileForm[email]'     => 'alice@example.com',
        ]);

        $client->submit($form);
        $client->followRedirect();

        $this->assertSelectorTextContains('h1', 'Bienvenue');
    }

    public function testSubmitInvalidProfileForm(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/edit');

        // Utilisation de valeurs "valide mais invalides selon validation"
        $form = $crawler->selectButton('Mettre à jour')->form([
            'editProfileForm[firstName]' => 'A', // trop court
            'editProfileForm[lastName]'  => 'B', // trop court
            'editProfileForm[email]'     => 'invalid-email', // email invalide
        ]);

        $crawler = $client->submit($form);

        $this->assertResponseStatusCodeSame(200);

        $this->assertFormErrorContains($crawler, 'trop courte');
        $this->assertFormErrorContains($crawler, 'adresse email valide');
    }

    public function testFormMaxLengthValidation(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/edit');

        $longString = str_repeat('a', 51); // 51 caractères

        $form = $crawler->selectButton('Mettre à jour')->form([
            'editProfileForm[firstName]' => $longString,
            'editProfileForm[lastName]'  => $longString,
            'editProfileForm[email]'     => 'maxlength@example.com',
        ]);

        $crawler = $client->submit($form);

        $this->assertResponseStatusCodeSame(200);

        $this->assertFormErrorContains($crawler, 'trop longue');
    }

    public function testFormMinLengthValidation(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/edit');

        $form = $crawler->selectButton('Mettre à jour')->form([
            'editProfileForm[firstName]' => 'A', // trop court
            'editProfileForm[lastName]'  => 'B', // trop court
            'editProfileForm[email]'     => 'short@example.com',
        ]);

        $crawler = $client->submit($form);

        $this->assertResponseStatusCodeSame(200);

        $this->assertFormErrorContains($crawler, 'trop courte');
    }

    public function testFormDoesNotModifyUnchangedValues(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/edit');

        $form = $crawler->selectButton('Mettre à jour')->form([
            'editProfileForm[firstName]' => 'John',
            'editProfileForm[lastName]'  => 'Doe',
            'editProfileForm[email]'     => $user->getEmail(),
        ]);

        $client->submit($form);
        $client->followRedirect();

        $this->assertSelectorTextContains('h1', 'Bienvenue');
    }

    public function testBackLinkNavigatesToDashboard(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/edit');

        $link = $crawler->selectLink('← Retour au dashboard')->link();
        $client->click($link);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Bienvenue');
    }
}