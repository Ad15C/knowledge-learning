<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\DataFixtures\TestUserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;

class ChangePasswordFormFlowTest extends WebTestCase
{
    private $client;
    private $em;
    private $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get('security.user_password_hasher');

        $this->createTestUser();
    }

    private function createTestUser(): void
    {
        $existingUser = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        if ($existingUser) {
            $this->em->remove($existingUser);
            $this->em->flush();
        }

        $user = new User();
        $user->setEmail(TestUserFixtures::USER_EMAIL);
        $user->setFirstName('Addie');
        $user->setLastName('C');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, TestUserFixtures::USER_PASSWORD));

        $this->em->persist($user);
        $this->em->flush();
    }

    public function testChangePasswordFlow(): void
    {
        // --- Connexion ---
        $crawler = $this->client->request('GET', '/login');
        $csrfToken = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $loginForm = $crawler->filter('form')->form([
            '_username' => TestUserFixtures::USER_EMAIL,
            '_password' => TestUserFixtures::USER_PASSWORD,
            '_csrf_token' => $csrfToken,
        ]);

        $this->client->submit($loginForm);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.sidebar-link.active');

        // --- Changement de mot de passe valide ---
        $crawler = $this->client->request('GET', '/dashboard/password');
        $this->assertResponseIsSuccessful();

        $csrfTokenPassword = $crawler->filter('input[name="change_password[_token]"]')->attr('value');

        $form = $crawler->filter('form[name="change_password"]')->form([
            'change_password[plainPassword][first]' => 'ValidPass123!',
            'change_password[plainPassword][second]' => 'ValidPass123!',
            'change_password[_token]' => $csrfTokenPassword,
        ]);

        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('.flash-success', 'Mot de passe mis à jour !');

        // --- Déconnexion ---
        $this->client->request('GET', '/logout');
        $this->client->followRedirect();

        // --- Reconnexion avec le nouveau mot de passe ---
        $crawler = $this->client->request('GET', '/login');
        $loginForm = $crawler->filter('form')->form([
            '_username' => TestUserFixtures::USER_EMAIL,
            '_password' => 'ValidPass123!',
            '_csrf_token' => $crawler->filter('input[name="_csrf_token"]')->attr('value'),
        ]);

        $this->client->submit($loginForm);
        $crawler = $this->client->followRedirect();
        $this->assertSelectorExists('.sidebar-link.active');
    }

    protected function tearDown(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        if ($user) {
            $this->em->remove($user);
            $this->em->flush();
        }

        $this->em = null;
        $this->client = null;
        $this->passwordHasher = null;

        parent::tearDown();
    }
}