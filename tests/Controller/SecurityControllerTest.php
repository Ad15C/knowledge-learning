<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Lesson;
use App\Entity\Cursus;
use App\Entity\Theme;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
    // -----------------------------
    // UTILITAIRES
    // -----------------------------
    private function createTestUser($client, $email = null, $password = 'Password1!')
    {
        $container = $client->getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        if (!$email) {
            $email = 'test_' . uniqid() . '@example.com';
        }

        $user = new User();
        $user->setEmail($email)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setPassword($passwordHasher->hashPassword($user, $password))
            ->setIsVerified(true);

        $em = $container->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createTestLesson($client, $title = 'Test Lesson', $lessonPrice = 10.0, $cursusPrice = 100.0): Lesson
    {
        $em = $client->getContainer()->get('doctrine')->getManager();

        $theme = new Theme();
        $theme->setName('Theme test');
        $em->persist($theme);

        $cursus = new Cursus();
        $cursus->setName('Cursus test')
            ->setPrice($cursusPrice)
            ->setTheme($theme);
        $em->persist($cursus);

        $lesson = new Lesson();
        $lesson->setTitle($title)
            ->setPrice($lessonPrice)
            ->setCursus($cursus);
        $em->persist($lesson);

        $em->flush();

        return $lesson;
    }

    // -----------------------------
    // LOGIN / AUTHENTIFICATION
    // -----------------------------
    public function testLoginPageAndAuthentication(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser($client);

        $crawler = $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');

        $form = $crawler->filter('#login-submit')->form([
            '_username' => $user->getEmail(),
            '_password' => 'Password1!',
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/dashboard');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/login');

        $form = $crawler->filter('#login-submit')->form([
            '_username' => $user->getEmail(),
            '_password' => 'WrongPassword!',
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/login');

        $client->followRedirect();
        $this->assertSelectorExists('.flash-error');

        $client->loginUser($user);

        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
    }

    // -----------------------------
    // LOGOUT
    // -----------------------------
    public function testLogoutDestroysSession(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser($client);

        $client->loginUser($user);
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/logout');
        $this->assertResponseRedirects('/login');

        $client->followRedirect();
        $client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/login');
    }

    // -----------------------------
    // SÉCURITÉ / ACCÈS PAGES PROTÉGÉES
    // -----------------------------
    public function testProtectedRoutesRequireLogin(): void
    {
        $client = static::createClient();
        $lesson = $this->createTestLesson($client);

        $protectedRoutes = [
            '/dashboard',
            '/dashboard/purchases',
            '/dashboard/certifications',
            '/lesson/' . $lesson->getId(),
        ];

        foreach ($protectedRoutes as $url) {
            $client->request('GET', $url);
            $this->assertResponseRedirects(
                '/login',
                302,
                "La route $url devrait rediriger vers /login pour un utilisateur non connecté."
            );
        }
    }

    public function testAuthenticatedUserCanAccessProtectedRoutes(): void
    {
        $client = static::createClient();
        $user = $this->createTestUser($client);
        $lesson = $this->createTestLesson($client);

        $client->loginUser($user);

        // Pages qui doivent répondre directement en 200
        $successfulRoutes = [
            '/dashboard',
            '/dashboard/purchases',
            '/dashboard/certifications',
        ];

        foreach ($successfulRoutes as $url) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful(
                "L'utilisateur connecté devrait accéder à $url sans problème."
            );
        }

        // /lesson/{id} a un comportement spécifique : redirection vers /cursus/{id}
        $client->request('GET', '/lesson/' . $lesson->getId());
        $this->assertTrue(
            $client->getResponse()->isRedirection(),
            "La route /lesson/{id} devrait rediriger pour un utilisateur connecté."
        );

        $location = (string) $client->getResponse()->headers->get('Location');
        $this->assertStringContainsString(
            '/cursus/' . $lesson->getCursus()?->getId(),
            $location
        );

        $client->followRedirect();
        $this->assertResponseIsSuccessful(
            "Après redirection depuis /lesson/{id}, la page cible devrait être accessible."
        );
    }
}