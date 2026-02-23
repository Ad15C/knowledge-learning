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

        // Créer un thème
        $theme = new Theme();
        $theme->setName('Theme test');
        $em->persist($theme);

        // Créer un cursus lié au thème
        $cursus = new Cursus();
        $cursus->setName('Cursus test')
               ->setPrice($cursusPrice)
               ->setTheme($theme);
        $em->persist($cursus);

        // Créer la leçon liée au cursus
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
    public function testLoginPageAndAuthentication()
    {
        $client = static::createClient();
        $user = $this->createTestUser($client);

        // Page login accessible
        $crawler = $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');

        // Login avec identifiants corrects
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $user->getEmail(),
            '_password' => 'Password1!',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/dashboard');

        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Bienvenue'); // adapte selon ton template

        // Login avec mot de passe incorrect
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $user->getEmail(),
            '_password' => 'WrongPassword!',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/login');

        $client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', 'invalid');
        $this->assertSelectorTextContains('.flash-error', 'Identifiants invalides');

        // Session persistante après refresh
        $client->loginUser($user);
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/dashboard'); // refresh
        $this->assertResponseIsSuccessful();
    }

    // -----------------------------
    // LOGOUT
    // -----------------------------
    public function testLogoutDestroysSession()
    {
        $client = static::createClient();
        $user = $this->createTestUser($client);

        $client->loginUser($user);
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        // logout
        $client->request('GET', '/logout');
        $this->assertResponseRedirects('/login');

        // Dashboard inaccessible après logout
        $client->followRedirect();
        $client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/login');
    }

    // -----------------------------
    // SÉCURITÉ / ACCÈS PAGES PROTÉGÉES
    // -----------------------------
    public function testProtectedRoutesRequireLogin()
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
            $this->assertResponseRedirects('/login', 302, "La route $url devrait rediriger vers /login pour un utilisateur non connecté.");
        }
    }

    public function testAuthenticatedUserCanAccessProtectedRoutes()
    {
        $client = static::createClient();
        $user = $this->createTestUser($client);
        $lesson = $this->createTestLesson($client);

        $client->loginUser($user);

        $protectedRoutes = [
            '/dashboard',
            '/dashboard/purchases',
            '/dashboard/certifications',
            '/lesson/' . $lesson->getId(),
        ];

        foreach ($protectedRoutes as $url) {
            $client->request('GET', $url);
            $this->assertResponseIsSuccessful("L'utilisateur connecté devrait accéder à $url sans problème.");
        }
    }
}