<?php

namespace App\Tests\Form;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordFormTypeTest extends WebTestCase
{
    private function createUserClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        return static::createClient([], [
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
        ]);
    }

    private function createAndPersistUser(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        string $email = 'change_password_test@example.com',
        string $plainPassword = 'Password1!'
    ): User {
        $container = $client->getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        // éviter collision si le test est relancé
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            $em->remove($existing);
            $em->flush();
        }

        $user = new User();
        $user->setEmail($email)
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setPassword($hasher->hashPassword($user, $plainPassword))
            ->setIsVerified(true);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function assertFormErrorContains(Crawler $crawler, string $expectedText): void
    {
        $errors = $crawler->filter('.form-error')->each(fn($node) => trim($node->text()));
        foreach ($errors as $error) {
            if (str_contains($error, $expectedText)) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail("Expected form error to contain: '$expectedText'. Actual errors: " . implode(' | ', $errors));
    }

    public function testChangePasswordUpdatesHashAndRedirects(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);

        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/password');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="change_password"]');

        $newPassword = 'NewPassword123!';

        $form = $crawler->selectButton('Modifier le mot de passe')->form([
            'change_password[plainPassword][first]' => $newPassword,
            'change_password[plainPassword][second]' => $newPassword,
        ]);

        $crawler = $client->submit($form);

        // Si pas de redirect -> on veut voir pourquoi (erreurs de validation)
        if (!$client->getResponse()->isRedirect()) {
            $this->assertResponseStatusCodeSame(200);
            $this->assertSelectorExists('.form-error');
            // Exemple d’erreur attendue si contraintes échouent :
            // $this->assertFormErrorContains($crawler, 'au moins');
        }

        $this->assertResponseRedirects('/dashboard');
        $client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', 'Mot de passe mis à jour !');

        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $client->getContainer()->get(UserPasswordHasherInterface::class);

        $em->clear();
        $userReloaded = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        $this->assertNotNull($userReloaded);

        $this->assertTrue($hasher->isPasswordValid($userReloaded, $newPassword));
    }

    public function testCanLoginWithNewPassword(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);

        $client->loginUser($user);

        // 1) Change password
        $crawler = $client->request('GET', '/dashboard/password');
        $this->assertResponseIsSuccessful();

        $newPassword = 'NewPassword123!';

        $form = $crawler->selectButton('Modifier le mot de passe')->form([
            'change_password[plainPassword][first]' => $newPassword,
            'change_password[plainPassword][second]' => $newPassword,
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/dashboard');

        // 2) Logout
        $client->request('GET', '/logout');

        // 3) Go to login + detect real field names
        $crawler = $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form();

        // Récupère les noms réels des champs dans le form
        $emailFieldName = null;
        $passwordFieldName = null;

        foreach ($loginForm->all() as $name => $field) {
            // heuristiques: email
            if ($emailFieldName === null) {
                if (str_contains($name, 'email') || str_contains($name, '_username') || str_contains($name, 'username')) {
                    $emailFieldName = $name;
                }
            }

            // heuristiques: password
            if ($passwordFieldName === null) {
                if (str_contains($name, 'password') || str_contains($name, '_password')) {
                    $passwordFieldName = $name;
                }
            }
        }

        $this->assertNotNull($emailFieldName, 'Champ email/username introuvable sur /login (name contient email|username|_username).');
        $this->assertNotNull($passwordFieldName, 'Champ password introuvable sur /login (name contient password|_password).');

        // Remplit le form avec les bons noms
        $loginForm[$emailFieldName] = $user->getEmail();
        $loginForm[$passwordFieldName] = $newPassword;

        $client->submit($loginForm);

        $this->assertResponseRedirects('/dashboard');
        $client->followRedirect();
        $this->assertSelectorExists('header.main-header');
    }
}