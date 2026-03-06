<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class AdminAccessTest extends WebTestCase
{
    private function createUser(KernelBrowser $client, array $roles = []): User
    {
        $container = $client->getContainer();
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $em = $container->get('doctrine')->getManager();

        $user = new User();
        $user->setEmail('test_' . uniqid('', true) . '@test.com')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setIsVerified(true)
            ->setRoles($roles)
            ->setPassword($hasher->hashPassword($user, 'Password1!'));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function assertAdminGetAccess(string $url): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient([], ['HTTPS' => 'on']);
        $client->request('GET', $url);
        $this->assertResponseRedirects('/login', 302);

        self::ensureKernelShutdown();
        $client = static::createClient([], ['HTTPS' => 'on']);
        $user = $this->createUser($client, ['ROLE_USER']);
        $client->loginUser($user);
        $client->request('GET', $url);
        $this->assertResponseStatusCodeSame(403);

        self::ensureKernelShutdown();
        $client = static::createClient([], ['HTTPS' => 'on']);
        $admin = $this->createUser($client, ['ROLE_ADMIN']);
        $client->loginUser($admin);
        $client->request('GET', $url);
        $this->assertResponseIsSuccessful();
    }

    public function testAdminGetRoutesAccess(): void
    {
        $routes = [
            '/admin',
            '/admin/users',
            '/admin/purchases',
            '/admin/cursus',
            '/admin/lesson',
            '/admin/themes',
            '/admin/contact/',
        ];

        foreach ($routes as $url) {
            $this->assertAdminGetAccess($url);
        }
    }

    public function testAdminUserShowRoute404(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient([], ['HTTPS' => 'on']);
        $admin = $this->createUser($client, ['ROLE_ADMIN']);

        $client->loginUser($admin);
        $client->request('GET', '/admin/users/999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAdminUserEditRoute404(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient([], ['HTTPS' => 'on']);
        $admin = $this->createUser($client, ['ROLE_ADMIN']);

        $client->loginUser($admin);
        $client->request('GET', '/admin/users/999999/edit');

        $this->assertResponseStatusCodeSame(404);
    }
}