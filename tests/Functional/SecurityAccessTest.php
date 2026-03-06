<?php

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class SecurityAccessTest extends WebTestCase
{
    private function getEm(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function getRouter(): RouterInterface
    {
        return static::getContainer()->get(RouterInterface::class);
    }

    private function createUser(array $storedRoles = []): User
    {
        $em = $this->getEm();

        $user = new User();
        $user
            ->setEmail(sprintf('test_%s_%s@example.com', uniqid('', true), bin2hex(random_bytes(4))))
            ->setFirstName('Test')
            ->setLastName('User')
            ->setIsVerified(true)
            ->setRoles($storedRoles)
            ->setPassword('dummy-hash')
            ->setCreatedAt(new \DateTimeImmutable('-1 day'));

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function test_admin_dashboard_redirects_guest_to_login_over_https(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);

        $client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(302);
        $this->assertResponseRedirects($this->getRouter()->generate('app_login'));
    }

    public function test_admin_themes_redirects_guest_to_login_over_https(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);

        $client->request('GET', '/admin/themes');

        $this->assertResponseStatusCodeSame(302);
        $this->assertResponseRedirects($this->getRouter()->generate('app_login'));
    }

    public function test_admin_route_returns_403_for_role_user(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $user = $this->createUser();

        $client->loginUser($user);
        $client->request('GET', '/admin');

        $this->assertResponseStatusCodeSame(403);
    }

    public function test_admin_themes_returns_403_for_role_user(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $user = $this->createUser();

        $client->loginUser($user);
        $client->request('GET', '/admin/themes');

        $this->assertResponseStatusCodeSame(403);
    }

    public function test_admin_dashboard_returns_200_for_role_admin(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $admin = $this->createUser(['ROLE_ADMIN']);

        $client->loginUser($admin);
        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
    }

    public function test_admin_themes_returns_200_for_role_admin(): void
    {
        $client = static::createClient([], ['HTTPS' => 'on']);
        $admin = $this->createUser(['ROLE_ADMIN']);

        $client->loginUser($admin);
        $client->request('GET', '/admin/themes');

        $this->assertResponseIsSuccessful();
    }

    public function test_dashboard_redirects_guest_to_login(): void
    {
        $client = static::createClient();

        $client->request('GET', '/dashboard');

        $this->assertResponseStatusCodeSame(302);
        $this->assertResponseRedirects($this->getRouter()->generate('app_login'));
    }

    public function test_dashboard_returns_200_for_role_user(): void
    {
        $client = static::createClient();
        $user = $this->createUser();

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
    }

    public function test_dashboard_redirects_admin_to_admin_dashboard(): void
    {
        $client = static::createClient();
        $admin = $this->createUser(['ROLE_ADMIN']);

        $client->loginUser($admin);
        $client->request('GET', '/dashboard');

        $this->assertResponseStatusCodeSame(302);
        $this->assertResponseRedirects($this->getRouter()->generate('admin_dashboard'));
    }

    public function test_admin_http_redirects_to_https(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin');

        $response = $client->getResponse();

        $this->assertTrue(in_array($response->getStatusCode(), [301, 302], true));
        $this->assertStringStartsWith('https://', $response->headers->get('Location', ''));
    }
}