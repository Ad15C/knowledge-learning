<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Purchase;
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

    private int $purchaseId;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        // Ton app force HTTP -> HTTPS (301). On force HTTPS pour éviter les redirections.
        $this->client = static::createClient([], [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
        ]);
        $this->client->disableReboot();

        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->router = $container->get(RouterInterface::class);

        $this->databaseTool = $container
            ->get(DatabaseToolCollection::class)
            ->get();

        $this->databaseTool->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);

        // Créer une commande de test (status cart)
        $user = $this->getUser();

        $purchase = new Purchase();
        $purchase->setUser($user);

        $this->em->persist($purchase);
        $this->em->flush();

        self::assertNotNull($purchase->getId(), 'Purchase ID should be generated.');
        $this->purchaseId = (int) $purchase->getId();
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

    private function reloadPurchase(): Purchase
    {
        $this->em->clear();

        $purchase = $this->em->getRepository(Purchase::class)->find($this->purchaseId);
        self::assertNotNull($purchase, 'Purchase should exist.');
        return $purchase;
    }

    /**
     * Récupère le CSRF token EXACTEMENT comme un navigateur :
     * - on va sur la page show (admin)
     * - on lit la value du champ hidden _token du formulaire de status
     *
     * => plus de problèmes de session/token storage.
     */
    private function fetchCsrfTokenFromShowPage(int $purchaseId): string
    {
        $crawler = $this->client->request('GET', '/admin/purchases/' . $purchaseId);
        self::assertResponseIsSuccessful();

        // On cherche le hidden input name="_token"
        // idéalement dans le formulaire de status. Si ton template n'a pas d'action explicite,
        // on prend le premier _token trouvé.
        $tokenNode = $crawler->filter('form input[name="_token"]');

        self::assertGreaterThan(
            0,
            $tokenNode->count(),
            'CSRF token input not found on show page. Check template admin/purchases/show.html.twig'
        );

        $token = (string) $tokenNode->first()->attr('value');
        self::assertNotSame('', $token, 'CSRF token value should not be empty.');

        return $token;
    }

    // ---------------------------
    // ROUTES
    // ---------------------------

    public function testAdminPurchaseRoutesAreCorrect(): void
    {
        $routes = $this->router->getRouteCollection();

        $expected = [
            'admin_purchase_index' => ['/admin/purchases', ['GET']],
            'admin_purchase_show'  => ['/admin/purchases/{id}', ['GET']],
            'admin_purchase_update_status' => ['/admin/purchases/{id}/status', ['POST']],
        ];

        foreach ($expected as $name => [$path, $methods]) {
            $route = $routes->get($name);

            self::assertNotNull($route, "Route $name inexistante");
            self::assertSame($path, $route->getPath(), "Path incorrect pour $name");
            self::assertSame($methods, $route->getMethods(), "Méthode HTTP incorrecte pour $name");
        }

        self::assertSame('\d+', $routes->get('admin_purchase_show')->getRequirement('id'));
        self::assertSame('\d+', $routes->get('admin_purchase_update_status')->getRequirement('id'));
    }

    // ---------------------------
    // SECURITY
    // ---------------------------

    public function testIndexAccessibleForAdmin(): void
    {
        $this->loginAsAdmin();
        $this->client->request('GET', '/admin/purchases');
        self::assertResponseIsSuccessful();
    }

    public function testIndexForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $this->client->request('GET', '/admin/purchases');
        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexRedirectsWhenAnonymous(): void
    {
        $this->client->request('GET', '/admin/purchases');
        $this->assertAnonymousRedirectsToLogin();
    }

    public function testShowAccessibleForAdmin(): void
    {
        $this->loginAsAdmin();
        $this->client->request('GET', '/admin/purchases/' . $this->purchaseId);
        self::assertResponseIsSuccessful();
    }

    public function testShowForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $this->client->request('GET', '/admin/purchases/' . $this->purchaseId);
        self::assertResponseStatusCodeSame(403);
    }

    public function testShowRedirectsWhenAnonymous(): void
    {
        $this->client->request('GET', '/admin/purchases/' . $this->purchaseId);
        $this->assertAnonymousRedirectsToLogin();
    }

    // ---------------------------
    // UPDATE STATUS
    // ---------------------------

    public function testUpdateStatusAccessibleForAdminAndChangesStatus(): void
    {
        $this->loginAsAdmin();

        // On récupère le token depuis la page show (même session navigateur)
        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $url = '/admin/purchases/' . $this->purchaseId . '/status';

        $this->client->request('POST', $url, [
            '_token' => $token,
            'status' => Purchase::STATUS_PAID,
        ]);

        self::assertTrue($this->client->getResponse()->isRedirection(), 'Should redirect back to show.');

        $purchase = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_PAID, $purchase->getStatus());
        self::assertNotNull($purchase->getPaidAt(), 'paidAt should be set when marking as paid.');
    }

    public function testUpdateStatusForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $url = '/admin/purchases/' . $this->purchaseId . '/status';

        $this->client->request('POST', $url, [
            '_token' => 'fake',
            'status' => Purchase::STATUS_PAID,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateStatusRedirectsWhenAnonymous(): void
    {
        $url = '/admin/purchases/' . $this->purchaseId . '/status';

        $this->client->request('POST', $url, [
            '_token' => 'fake',
            'status' => Purchase::STATUS_PAID,
        ]);

        $this->assertAnonymousRedirectsToLogin();
    }

    public function testUpdateStatusRejectsInvalidStatus(): void
    {
        $this->loginAsAdmin();

        $before = $this->reloadPurchase()->getStatus();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);
        $url = '/admin/purchases/' . $this->purchaseId . '/status';

        $this->client->request('POST', $url, [
            '_token' => $token,
            'status' => 'invalid_status_value',
        ]);

        self::assertTrue($this->client->getResponse()->isRedirection());

        $after = $this->reloadPurchase()->getStatus();
        self::assertSame($before, $after, 'Status should not change when invalid status is posted.');
    }

    public function testUpdateStatusRejectsForbiddenTransition(): void
    {
        $this->loginAsAdmin();

        // Force purchase to paid first
        $purchase = $this->reloadPurchase();
        $purchase->markPaid(new \DateTimeImmutable());
        $this->em->flush();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);
        $url = '/admin/purchases/' . $this->purchaseId . '/status';

        // paid -> cart forbidden
        $this->client->request('POST', $url, [
            '_token' => $token,
            'status' => Purchase::STATUS_CART,
        ]);

        self::assertTrue($this->client->getResponse()->isRedirection());

        $reloaded = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_PAID, $reloaded->getStatus(), 'Forbidden transition should not change status.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}