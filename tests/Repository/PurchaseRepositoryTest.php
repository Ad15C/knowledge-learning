<?php

namespace App\Tests\Repository;

use App\DataFixtures\TestUserFixtures;
use App\Entity\Purchase;
use App\Entity\User;
use App\Repository\PurchaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PurchaseRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PurchaseRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            TestUserFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);

        $repo = $this->em->getRepository(Purchase::class);
        self::assertInstanceOf(PurchaseRepository::class, $repo);
        $this->repo = $repo;

        $this->em->clear();
    }

    private function getTestUser(): User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user);
        return $user;
    }

    private function forceTotal(Purchase $purchase, string $total): void
    {
        $ref = new \ReflectionClass($purchase);
        $prop = $ref->getProperty('total');
        $prop->setAccessible(true);
        $prop->setValue($purchase, $total);
    }

    private function forceCreatedAt(Purchase $purchase, \DateTimeImmutable $dt): void
    {
        $ref = new \ReflectionClass($purchase);
        $prop = $ref->getProperty('createdAt');
        $prop->setAccessible(true);
        $prop->setValue($purchase, $dt);
    }

    private function createPurchase(User $user, string $status, string $total = '0.00'): Purchase
    {
        $p = new Purchase();
        $p->setUser($user)->setStatus($status);

        if ($status === Purchase::STATUS_PAID) {
            $p->setPaidAt(new \DateTimeImmutable('2026-02-01 10:00:00'));
        }

        // En prod c’est PrePersist, mais en test on le déclenche
        $p->generateOrderNumber();

        // total est une string (decimal), on force si on veut des valeurs propres
        $this->forceTotal($p, $total);

        $this->em->persist($p);
        return $p;
    }

    public function testFindByUserEmpty(): void
    {
        $user = $this->getTestUser();

        $orders = $this->repo->findByUser($user);

        self::assertIsArray($orders);
        self::assertCount(0, $orders);

        // bonus : totals
        self::assertSame(0, $this->repo->getTotalOrders($user));
        self::assertEqualsWithDelta(0.0, $this->repo->getTotalSpent($user), 0.0001);
    }

    public function testFindByUserAndStatus(): void
    {
        $user = $this->getTestUser();

        $this->createPurchase($user, Purchase::STATUS_CART, '0.00');
        $this->createPurchase($user, Purchase::STATUS_PAID, '10.00');

        $this->em->flush();
        $this->em->clear();

        $userReloaded = $this->em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($userReloaded);

        $paid = $this->repo->findByUserAndStatus($userReloaded, Purchase::STATUS_PAID);
        self::assertCount(1, $paid);
        self::assertSame(Purchase::STATUS_PAID, $paid[0]->getStatus());

        $cart = $this->repo->findByUserAndStatus($userReloaded, Purchase::STATUS_CART);
        self::assertCount(1, $cart);
        self::assertSame(Purchase::STATUS_CART, $cart[0]->getStatus());
    }

    public function testFindByUserAndPeriod(): void
    {
        $user = $this->getTestUser();

        $old = $this->createPurchase($user, Purchase::STATUS_PAID, '5.00');
        $this->forceCreatedAt($old, new \DateTimeImmutable('2024-01-01 10:00:00'));

        $inRange = $this->createPurchase($user, Purchase::STATUS_PAID, '7.00');
        $this->forceCreatedAt($inRange, new \DateTimeImmutable('2026-02-01 10:00:00'));

        $this->em->flush();
        $this->em->clear();

        $userReloaded = $this->em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($userReloaded);

        $from = new \DateTimeImmutable('2026-01-01 00:00:00');
        $to   = new \DateTimeImmutable('2026-03-01 00:00:00');

        $results = $this->repo->findByUserAndPeriod($userReloaded, $from, $to);

        self::assertCount(1, $results);
        self::assertSame($inRange->getOrderNumber(), $results[0]->getOrderNumber());
    }

    public function testGetTotalOrdersAndSpentWithAndWithoutStatus(): void
    {
        $user = $this->getTestUser();

        $this->createPurchase($user, Purchase::STATUS_PAID, '10.00');
        $this->createPurchase($user, Purchase::STATUS_PAID, '20.00');
        $this->createPurchase($user, Purchase::STATUS_CART, '99.00');

        $this->em->flush();
        $this->em->clear();

        $userReloaded = $this->em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($userReloaded);

        // sans filtre status
        $expectedTotal = 10.00 + 20.00 + 99.00;
        self::assertSame(3, $this->repo->getTotalOrders($userReloaded));
        self::assertEqualsWithDelta($expectedTotal, $this->repo->getTotalSpent($userReloaded), 0.0001);

        // avec filtre paid
        $expectedPaid = 10.00 + 20.00;
        self::assertSame(2, $this->repo->getTotalOrders($userReloaded, Purchase::STATUS_PAID));
        self::assertEqualsWithDelta($expectedPaid, $this->repo->getTotalSpent($userReloaded, Purchase::STATUS_PAID), 0.0001);
    }

    public function testFindForAdminListPaginatedCanFilterAndPaginate(): void
    {
        $user = $this->getTestUser();

        // 3 commandes: 2 paid, 1 cart
        $p1 = $this->createPurchase($user, Purchase::STATUS_PAID, '10.00');
        $this->forceCreatedAt($p1, new \DateTimeImmutable('2026-02-01 10:00:00'));

        $p2 = $this->createPurchase($user, Purchase::STATUS_PAID, '20.00');
        $this->forceCreatedAt($p2, new \DateTimeImmutable('2026-02-02 10:00:00'));

        $p3 = $this->createPurchase($user, Purchase::STATUS_CART, '0.00');
        $this->forceCreatedAt($p3, new \DateTimeImmutable('2026-02-03 10:00:00'));

        $this->em->flush();
        $this->em->clear();

        $result = $this->repo->findForAdminListPaginated(
            q: '',
            status: Purchase::STATUS_PAID,
            userId: $user->getId(),
            dateFrom: new \DateTimeImmutable('2026-02-01 00:00:00'),
            dateTo: new \DateTimeImmutable('2026-02-28 00:00:00'),
            sort: 'createdAt',
            dir: 'DESC',
            page: 1,
            perPage: 10
        );

        self::assertArrayHasKey('items', $result);
        self::assertArrayHasKey('total', $result);

        self::assertSame(2, $result['total']);
        self::assertCount(2, $result['items']);
        self::assertSame(Purchase::STATUS_PAID, $result['items'][0]->getStatus());
        self::assertSame(Purchase::STATUS_PAID, $result['items'][1]->getStatus());
    }

    public function testFindOneForAdminShowLoadsUserAndItemsJoins(): void
    {
        $user = $this->getTestUser();

        $purchase = $this->createPurchase($user, Purchase::STATUS_PAID, '10.00');
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->repo->findOneForAdminShow($purchase->getId());

        self::assertNotNull($loaded);
        self::assertNotNull($loaded->getUser());
        self::assertSame($user->getId(), $loaded->getUser()->getId());

        // items peuvent être vides ici, mais la jointure doit fonctionner
        self::assertNotNull($loaded->getItems());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em, $this->repo);
        self::ensureKernelShutdown();
    }
}