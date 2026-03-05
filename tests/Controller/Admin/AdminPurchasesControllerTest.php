<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

class AdminPurchasesControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private RouterInterface $router;
    private $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->router = static::getContainer()->get(RouterInterface::class);

        $this->databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();

        $this->databaseTool->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);
    }

    private function getAdmin(): User
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture not found.');
        return $admin;
    }

    private function getUser(): User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture not found.');
        return $user;
    }

    private function loginAsAdmin(): void
    {
        $this->client->loginUser($this->getAdmin());
    }

    private function loginAsUser(): void
    {
        $this->client->loginUser($this->getUser());
    }

    private function assertAnonymousRedirectsToLogin(): void
    {
        self::assertTrue($this->client->getResponse()->isRedirection(), 'Anonymous should be redirected.');
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
    }

    // ---------------------------
    // ROUTES: existence + config
    // ---------------------------

    public function testAdminPurchaseRoutesAreCorrect(): void
    {
        $routes = $this->router->getRouteCollection();

        $expected = [
            'admin_purchase_index' => '/admin/purchases',
            'admin_purchase_show'  => '/admin/purchases/{id}',
        ];

        foreach ($expected as $name => $path) {
            $route = $routes->get($name);

            self::assertNotNull($route, "Route $name inexistante");
            self::assertSame($path, $route->getPath(), "Path incorrect pour $name");
            self::assertSame(['GET'], $route->getMethods(), "Méthode HTTP incorrecte pour $name");
        }

        self::assertSame('\d+', $routes->get('admin_purchase_show')->getRequirement('id'));
    }

    // ---------------------------
    // SECURITY + controller responds
    // ---------------------------

    public function testIndexAccessibleForAdmin(): void
    {
        $this->loginAsAdmin();

        // IMPORTANT: utiliser https pour éviter le 301 “canonical https”
        $this->client->request('GET', 'https://localhost/admin/purchases');

        self::assertResponseIsSuccessful();
    }

    public function testIndexForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $this->client->request('GET', 'https://localhost/admin/purchases');

        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexRedirectsWhenAnonymous(): void
    {
        $this->client->request('GET', 'https://localhost/admin/purchases');

        $this->assertAnonymousRedirectsToLogin();
    }

    public function testShowForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $this->client->request('GET', 'https://localhost/admin/purchases/1');

        self::assertResponseStatusCodeSame(403);
    }

    public function testShowRedirectsWhenAnonymous(): void
    {
        $this->client->request('GET', 'https://localhost/admin/purchases/1');

        $this->assertAnonymousRedirectsToLogin();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}