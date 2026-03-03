<?php

namespace App\Tests\Form;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordFormTypeTest extends WebTestCase
{
    private function createUserClient(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return static::createClient([], [
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
        ]);
    }

    private function startSessionIfNeeded(KernelBrowser $client): void
    {
        $request = $client->getRequest();
        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            if (!$session->isStarted()) {
                $session->start();
            }
        }
    }

    private function createAndPersistUser(
        KernelBrowser $client,
        string $email = 'change_password_test@example.com',
        string $plainPassword = 'Password1!'
    ): User {
        $container = self::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

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
        $errors = [];

        foreach (['.form-error', '.invalid-feedback', '.form-error-message', '.form-errors li'] as $selector) {
            if ($crawler->filter($selector)->count() > 0) {
                $errors = array_merge(
                    $errors,
                    $crawler->filter($selector)->each(fn ($n) => trim($n->text()))
                );
            }
        }

        foreach ($errors as $error) {
            if (str_contains($error, $expectedText)) {
                self::assertTrue(true);
                return;
            }
        }

        $this->fail(
            "Expected form error to contain: '$expectedText'. Actual errors: " . implode(' | ', $errors)
        );
    }

    private function findLoginForm(Crawler $crawler): \Symfony\Component\DomCrawler\Form
    {
        // 1) Si tu as un bouton "Se connecter"
        if ($crawler->selectButton('Se connecter')->count() > 0) {
            return $crawler->selectButton('Se connecter')->form();
        }

        // 2) Sinon: premier <form> qui contient un input[type=password]
        if ($crawler->filter('form input[type="password"]')->count() > 0) {
            return $crawler->filter('form input[type="password"]')->first()->form();
        }

        // 3) Sinon: fallback sur le premier form
        if ($crawler->filter('form')->count() > 0) {
            return $crawler->filter('form')->first()->form();
        }

        $this->fail('Formulaire de login introuvable sur /login.');
    }

    public function testChangePasswordUpdatesHashAndRedirects(): void
    {
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);

        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/password');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="change_password"]');

        $this->startSessionIfNeeded($client);

        $newPassword = 'NewPassword123!';

        $form = $crawler->filter('form[name="change_password"]')->form([
            'change_password[plainPassword][first]' => $newPassword,
            'change_password[plainPassword][second]' => $newPassword,
        ]);

        $client->submit($form);

        if (!$client->getResponse()->isRedirect()) {
            self::assertResponseStatusCodeSame(200);
            // option debug:
            // $this->assertFormErrorContains($crawler, 'au moins');
        }

        self::assertResponseRedirects('/dashboard');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-success, .flash.flash-success, .flash, .alert-success');

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $em->clear();
        $userReloaded = $em->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
        self::assertNotNull($userReloaded);

        self::assertTrue($hasher->isPasswordValid($userReloaded, $newPassword));
    }

    public function testCanLoginWithNewPassword(): void
    {
        // 1) change password (client connecté)
        $client = $this->createUserClient();
        $user = $this->createAndPersistUser($client);

        $client->loginUser($user);

        $crawler = $client->request('GET', '/dashboard/password');
        self::assertResponseIsSuccessful();

        $this->startSessionIfNeeded($client);

        $newPassword = 'NewPassword123!';

        $form = $crawler->filter('form[name="change_password"]')->form([
            'change_password[plainPassword][first]' => $newPassword,
            'change_password[plainPassword][second]' => $newPassword,
        ]);

        $client->submit($form);
        self::assertResponseRedirects('/dashboard');
        $client->followRedirect();

        // 2) nouveau client (session clean) => login avec nouveau mdp
        $client2 = $this->createUserClient();
        $crawler2 = $client2->request('GET', '/login');
        self::assertResponseIsSuccessful();

        $loginForm = $this->findLoginForm($crawler2);

        // détecte les champs
        $emailFieldName = null;
        $passwordFieldName = null;

        foreach ($loginForm->all() as $name => $field) {
            if ($emailFieldName === null && (str_contains($name, 'email') || str_contains($name, '_username') || str_contains($name, 'username'))) {
                $emailFieldName = $name;
            }
            if ($passwordFieldName === null && (str_contains($name, 'password') || str_contains($name, '_password'))) {
                $passwordFieldName = $name;
            }
        }

        self::assertNotNull($emailFieldName, 'Champ email/username introuvable sur /login.');
        self::assertNotNull($passwordFieldName, 'Champ password introuvable sur /login.');

        $loginForm[$emailFieldName] = $user->getEmail();
        $loginForm[$passwordFieldName] = $newPassword;

        $client2->submit($loginForm);

        self::assertResponseRedirects('/dashboard');
        $client2->followRedirect();
        self::assertResponseIsSuccessful();
    }
}