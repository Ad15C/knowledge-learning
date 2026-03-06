<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityLoginTest extends WebTestCase
{
    private function createUser(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        string $email,
        string $plainPassword,
        array $roles = ['ROLE_USER']
    ): void {
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Addie');
        $user->setLastName('Test');
        $user->setRoles($roles);
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

        if (method_exists($user, 'setIsVerified')) {
            $user->setIsVerified(true);
        }

        if (method_exists($user, 'setCreatedAt')) {
            $user->setCreatedAt(new \DateTimeImmutable());
        }

        if (method_exists($user, 'setUpdatedAt')) {
            $user->setUpdatedAt(new \DateTimeImmutable());
        }

        $em->persist($user);
        $em->flush();
    }

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testLoginFormContainsExpectedFields(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertGreaterThan(0, $crawler->filter('input[name="_username"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('input[name="_password"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('input[name="_csrf_token"]')->count());
    }

    public function testLoginFormPostsToAppLogin(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->filter('form')->first();
        $this->assertSame('/login', $form->attr('action'));
        $this->assertSame('post', strtolower((string) $form->attr('method')));
    }

    public function testSuccessfulLoginRedirectsToDashboard(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'user-test@example.com';
        $password = 'Test1234!';

        $this->createUser($em, $passwordHasher, $email, $password, ['ROLE_USER']);

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => $password,
        ]);

        $client->submit($form);

        $this->assertResponseRedirects('/dashboard');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithoutCsrfTokenIsRejected(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'user-no-csrf@example.com';
        $password = 'Test1234!';

        $this->createUser($em, $passwordHasher, $email, $password, ['ROLE_USER']);

        $client->request('POST', '/login', [
            '_username' => $email,
            '_password' => $password,
        ]);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.flash-error');
    }

    public function testLoginWithInvalidCsrfTokenIsRejected(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'user-invalid-csrf@example.com';
        $password = 'Test1234!';

        $this->createUser($em, $passwordHasher, $email, $password, ['ROLE_USER']);

        $client->request('POST', '/login', [
            '_username' => $email,
            '_password' => $password,
            '_csrf_token' => 'token_invalide',
        ]);

        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.flash-error');
    }

    public function testNonAdminTryingToAccessAdminBeforeLoginGets403AfterLogin(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $email = 'user-admin-target@example.com';
        $password = 'Test1234!';

        $this->createUser($em, $passwordHasher, $email, $password, ['ROLE_USER']);

        // 1. Accès initial à /admin en HTTP
        $client->request('GET', '/admin');

        // 2. Symfony force d'abord le HTTPS
        $this->assertResponseRedirects('https://localhost/admin', 301);

        // 3. On suit la redirection HTTPS
        $client->followRedirect();

        // 4. Une fois en HTTPS, l'utilisateur non connecté doit être redirigé vers /login
        $this->assertResponseRedirects('/login');

        $crawler = $client->followRedirect();

        // 5. Connexion avec un utilisateur non admin
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => $email,
            '_password' => $password,
        ]);

        $client->submit($form);

        // 6. Après login, retour vers la target path /admin
        $this->assertResponseRedirects('/admin');

        $client->followRedirect();

        // 7. L'utilisateur connecté mais non admin doit recevoir un 403
        $this->assertResponseStatusCodeSame(403);
    }
}