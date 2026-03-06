<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\PurchaseFixtures;
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
            PurchaseFixtures::class,
        ]);

        $user = $this->getUser();

        $purchase = new Purchase();
        $purchase->setUser($user);

        $this->em->persist($purchase);
        $this->em->flush();

        self::assertNotNull($purchase->getId());
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
        $this->client->loginUser($this->getAdmin(), 'main');
    }

    private function loginAsUser(): void
    {
        $this->client->loginUser($this->getUser(), 'main');
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

    private function fetchCsrfTokenFromShowPage(int $purchaseId): string
    {
        $crawler = $this->client->request('GET', '/admin/purchases/' . $purchaseId);
        self::assertResponseIsSuccessful();

        $tokenNode = $crawler->filter('form input[name="_token"]');

        self::assertGreaterThan(
            0,
            $tokenNode->count(),
            'CSRF token input not found on show page. Check template admin/purchases/show.html.twig'
        );

        $token = (string) $tokenNode->first()->attr('value');
        self::assertNotSame('', $token);

        return $token;
    }

    public function testAdminPurchaseRoutesAreCorrect(): void
    {
        $routes = $this->router->getRouteCollection();

        $expected = [
            'admin_purchase_index' => ['/admin/purchases', ['GET']],
            'admin_purchase_show' => ['/admin/purchases/{id}', ['GET']],
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

    public function testIndexAcceptsValidFilters(): void
    {
        $this->loginAsAdmin();

        $user = $this->getUser();

        $this->client->request('GET', '/admin/purchases', [
            'q' => 'test',
            'status' => Purchase::STATUS_PAID,
            'user' => (string) $user->getId(),
            'dateFrom' => '2026-01-01',
            'dateTo' => '2026-01-31',
            'sort' => 'total',
            'dir' => 'ASC',
            'page' => '2',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testIndexIgnoresInvalidStatusSortDirAndDates(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin/purchases', [
            'status' => 'not_allowed',
            'sort' => 'hack',
            'dir' => 'SIDEWAYS',
            'dateFrom' => '2026-2-01',
            'dateTo' => 'invalid-date',
            'page' => '-5',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body');
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

    public function testShowReturns404WhenPurchaseDoesNotExist(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin/purchases/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateStatusAccessibleForAdminAndChangesCartToPaid(): void
    {
        $this->loginAsAdmin();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => $token,
            'status' => Purchase::STATUS_PAID,
        ]);

        self::assertResponseRedirects('/admin/purchases/' . $this->purchaseId);

        $purchase = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_PAID, $purchase->getStatus());
        self::assertNotNull($purchase->getPaidAt());
    }

    public function testUpdateStatusAccessibleForAdminAndChangesCartToPending(): void
    {
        $this->loginAsAdmin();

        $purchase = $this->reloadPurchase();
        $purchase->setPaidAt(new \DateTimeImmutable());
        $this->em->flush();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => $token,
            'status' => Purchase::STATUS_PENDING,
        ]);

        self::assertResponseRedirects('/admin/purchases/' . $this->purchaseId);

        $reloaded = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_PENDING, $reloaded->getStatus());
        self::assertNull($reloaded->getPaidAt());
    }

    public function testUpdateStatusAccessibleForAdminAndChangesCartToCanceled(): void
    {
        $this->loginAsAdmin();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => $token,
            'status' => Purchase::STATUS_CANCELED,
        ]);

        self::assertResponseRedirects('/admin/purchases/' . $this->purchaseId);

        $reloaded = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_CANCELED, $reloaded->getStatus());
    }

    public function testUpdateStatusPendingToPaidSetsPaidAt(): void
    {
        $this->loginAsAdmin();

        $purchase = $this->reloadPurchase();
        $purchase->markPending();
        $purchase->setPaidAt(null);
        $this->em->flush();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => $token,
            'status' => Purchase::STATUS_PAID,
        ]);

        self::assertResponseRedirects('/admin/purchases/' . $this->purchaseId);

        $reloaded = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_PAID, $reloaded->getStatus());
        self::assertNotNull($reloaded->getPaidAt());
    }

    public function testUpdateStatusPendingToCanceledKeepsPaidAtNull(): void
    {
        $this->loginAsAdmin();

        $purchase = $this->reloadPurchase();
        $purchase->markPending();
        $purchase->setPaidAt(null);
        $this->em->flush();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => $token,
            'status' => Purchase::STATUS_CANCELED,
        ]);

        self::assertResponseRedirects('/admin/purchases/' . $this->purchaseId);

        $reloaded = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_CANCELED, $reloaded->getStatus());
        self::assertNull($reloaded->getPaidAt());
    }

    public function testUpdateStatusPaidToCanceledRemovesPaidAt(): void
    {
        $this->loginAsAdmin();

        $purchase = $this->reloadPurchase();
        $purchase->markPaid(new \DateTimeImmutable());
        $this->em->flush();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => $token,
            'status' => Purchase::STATUS_CANCELED,
        ]);

        self::assertResponseRedirects('/admin/purchases/' . $this->purchaseId);

        $reloaded = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_CANCELED, $reloaded->getStatus());
        self::assertNull($reloaded->getPaidAt());
    }

    public function testUpdateStatusSameStatusDoesNothing(): void
    {
        $this->loginAsAdmin();

        $purchase = $this->reloadPurchase();
        $purchase->markPending();
        $purchase->setPaidAt(null);
        $this->em->flush();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => $token,
            'status' => Purchase::STATUS_PENDING,
        ]);

        self::assertResponseRedirects('/admin/purchases/' . $this->purchaseId);

        $reloaded = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_PENDING, $reloaded->getStatus());
        self::assertNull($reloaded->getPaidAt());
    }

    public function testUpdateStatusRejectsInvalidStatus(): void
    {
        $this->loginAsAdmin();

        $before = $this->reloadPurchase();
        $beforeStatus = $before->getStatus();
        $beforePaidAt = $before->getPaidAt();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => $token,
            'status' => 'invalid_status_value',
        ]);

        self::assertResponseRedirects('/admin/purchases/' . $this->purchaseId);

        $after = $this->reloadPurchase();
        self::assertSame($beforeStatus, $after->getStatus());
        self::assertEquals($beforePaidAt, $after->getPaidAt());
    }

    public function testUpdateStatusRejectsForbiddenTransition(): void
    {
        $this->loginAsAdmin();

        $purchase = $this->reloadPurchase();
        $purchase->markPaid(new \DateTimeImmutable());
        $this->em->flush();

        $token = $this->fetchCsrfTokenFromShowPage($this->purchaseId);

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => $token,
            'status' => Purchase::STATUS_CART,
        ]);

        self::assertResponseRedirects('/admin/purchases/' . $this->purchaseId);

        $reloaded = $this->reloadPurchase();
        self::assertSame(Purchase::STATUS_PAID, $reloaded->getStatus());
        self::assertNotNull($reloaded->getPaidAt());
    }

    public function testUpdateStatusRejectsInvalidCsrf(): void
    {
        $this->loginAsAdmin();

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => 'bad-token',
            'status' => Purchase::STATUS_PAID,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateStatusRejectsMissingCsrf(): void
    {
        $this->loginAsAdmin();

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            'status' => Purchase::STATUS_PAID,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateStatusReturns404WhenPurchaseDoesNotExist(): void
    {
        $this->loginAsAdmin();

        $this->client->request('POST', '/admin/purchases/999999/status', [
            '_token' => 'whatever',
            'status' => Purchase::STATUS_PAID,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUpdateStatusForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => 'fake',
            'status' => Purchase::STATUS_PAID,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateStatusRedirectsWhenAnonymous(): void
    {
        $this->client->request('POST', '/admin/purchases/' . $this->purchaseId . '/status', [
            '_token' => 'fake',
            'status' => Purchase::STATUS_PAID,
        ]);

        $this->assertAnonymousRedirectsToLogin();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}