<?php

namespace App\Tests\Controller;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
    private function createUserWithStatus(
        KernelBrowser $client,
        bool $isVerified = true,
        bool $isArchived = false,
        array $roles = [],
        ?string $email = null,
        string $password = 'Password1!'
    ): User {
        $container = $client->getContainer();
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        if (!$email) {
            $email = 'test_' . uniqid('', true) . '@example.com';
        }

        $user = new User();
        $user->setEmail($email)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setIsVerified($isVerified)
            ->setRoles($roles)
            ->setPassword($passwordHasher->hashPassword($user, $password));

        if ($isArchived) {
            $user->setArchivedAt(new \DateTimeImmutable('-1 day'));
        }

        $em = $container->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createTestLesson(
        KernelBrowser $client,
        string $title = 'Test Lesson',
        float $lessonPrice = 10.0,
        float $cursusPrice = 100.0
    ): Lesson {
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

    private function submitLoginForm(
        KernelBrowser $client,
        string $email,
        string $password
    ): void {
        $crawler = $client->request('GET', '/login');

        $form = $crawler->filter('#login-submit')->form();
        $form['_username'] = $email;
        $form['_password'] = $password;

        $client->submit($form);
    }

    private function submitLoginFormWithRememberMe(
        KernelBrowser $client,
        string $email,
        string $password
    ): void {
        $crawler = $client->request('GET', '/login');

        $form = $crawler->filter('#login-submit')->form();
        $form['_username'] = $email;
        $form['_password'] = $password;
        $form['_remember_me'] = 'on';

        $client->submit($form);
    }

    public function testLoginPageAndAuthentication(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithStatus($client, true, false);

        $crawler = $client->request('GET', '/login');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');

        $form = $crawler->filter('#login-submit')->form();
        $form['_username'] = $user->getEmail();
        $form['_password'] = 'Password1!';

        $client->submit($form);

        $this->assertResponseRedirects('/dashboard');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->submitLoginForm($client, $user->getEmail(), 'WrongPassword!');

        $this->assertResponseRedirects('/login');

        $client->followRedirect();
        $this->assertSelectorExists('.flash-error');

        $client->loginUser($user);

        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
    }

    public function testRememberMeCookieIsCreated(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithStatus($client, true, false);

        $this->submitLoginFormWithRememberMe($client, $user->getEmail(), 'Password1!');

        $this->assertResponseRedirects('/dashboard');

        $cookies = $client->getResponse()->headers->getCookies();

        $found = false;
        foreach ($cookies as $cookie) {
            if (\in_array($cookie->getName(), ['REMEMBERME', 'remember_me'], true)) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Le cookie remember-me doit être créé.');
    }

    public function testLogoutDestroysSession(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithStatus($client, true, false);

        $client->loginUser($user);
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/logout');
        $this->assertResponseRedirects('/login');

        $client->followRedirect();
        $client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/login');
    }

    public function testLogoutDeletesRememberMeCookie(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithStatus($client, true, false);

        $this->submitLoginFormWithRememberMe($client, $user->getEmail(), 'Password1!');
        $this->assertResponseRedirects('/dashboard');

        $client->request('GET', '/logout');
        $this->assertResponseRedirects('/login');

        $cookies = $client->getResponse()->headers->getCookies();

        $foundDeletedCookie = false;
        foreach ($cookies as $cookie) {
            if (\in_array($cookie->getName(), ['REMEMBERME', 'remember_me'], true) && $cookie->getExpiresTime() <= time()) {
                $foundDeletedCookie = true;
                break;
            }
        }

        $this->assertTrue($foundDeletedCookie, 'Le cookie remember-me doit être supprimé au logout.');
    }

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
        $user = $this->createUserWithStatus($client, true, false);
        $lesson = $this->createTestLesson($client);

        $client->loginUser($user);

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

    public function testUnverifiedUserCannotLogin(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithStatus($client, false, false);

        $this->submitLoginForm($client, $user->getEmail(), 'Password1!');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();

        $this->assertSelectorTextContains(
            '.flash-error',
            'Votre compte n’est pas encore vérifié.'
        );
    }

    public function testArchivedUserCannotLogin(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithStatus($client, true, true);

        $this->submitLoginForm($client, $user->getEmail(), 'Password1!');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();

        $this->assertSelectorTextContains(
            '.flash-error',
            'Votre compte est archivé. Contactez un administrateur.'
        );
    }

    public function testUnverifiedAdminCannotLogin(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithStatus($client, false, false, ['ROLE_ADMIN']);

        $this->submitLoginForm($client, $user->getEmail(), 'Password1!');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();

        $this->assertSelectorTextContains(
            '.flash-error',
            'Votre compte n’est pas encore vérifié.'
        );
    }

    public function testArchivedAdminCannotLogin(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithStatus($client, true, true, ['ROLE_ADMIN']);

        $this->submitLoginForm($client, $user->getEmail(), 'Password1!');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();

        $this->assertSelectorTextContains(
            '.flash-error',
            'Votre compte est archivé. Contactez un administrateur.'
        );
    }

    public function testAdminPageRequiresAdmin(): void
    {
        $client = static::createClient();

        $user = $this->createUserWithStatus($client, true, false);

        $client->loginUser($user);

        $client->request('GET', 'https://localhost/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testArchivedAndUnverifiedUserCannotLoginWithArchivedMessageFirst(): void
    {
        $client = static::createClient();
        $user = $this->createUserWithStatus($client, false, true);

        $this->submitLoginForm($client, $user->getEmail(), 'Password1!');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();

        $this->assertSelectorTextContains(
            '.flash-error',
            'Votre compte est archivé. Contactez un administrateur.'
        );
    }
}