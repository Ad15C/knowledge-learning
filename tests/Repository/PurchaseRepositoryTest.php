<?php

namespace App\Tests\Repository;

use App\DataFixtures\TestUserFixtures;
use App\Entity\Purchase;
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
        self::bootKernel();

        $container = self::getContainer();

        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            TestUserFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(PurchaseRepository::class);
    }

    private function getTestUser(): \App\Entity\User
    {
        $user = $this->em->getRepository(\App\Entity\User::class)
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

    public function testFindByUserEmpty(): void
    {
        $user = $this->getTestUser();

        $orders = $this->repo->findByUser($user);

        self::assertIsArray($orders);
        self::assertCount(0, $orders);
    }

    public function testFindByUserAndStatus(): void
    {
        $user = $this->getTestUser();

        $pCart = (new Purchase())->setUser($user)->setStatus('cart');
        $pCart->generateOrderNumber();
        $this->forceTotal($pCart, '0.00');

        $pPaid = (new Purchase())->setUser($user)->setStatus('paid')->setPaidAt(new \DateTimeImmutable());
        $pPaid->generateOrderNumber();
        $this->forceTotal($pPaid, '10.00');

        $this->em->persist($pCart);
        $this->em->persist($pPaid);
        $this->em->flush();

        $paid = $this->repo->findByUserAndStatus($user, 'paid');
        self::assertCount(1, $paid);
        self::assertSame('paid', $paid[0]->getStatus());

        $cart = $this->repo->findByUserAndStatus($user, 'cart');
        self::assertCount(1, $cart);
        self::assertSame('cart', $cart[0]->getStatus());
    }

    public function testFindByUserAndPeriod(): void
    {
        $user = $this->getTestUser();

        $old = (new Purchase())->setUser($user)->setStatus('paid')->setPaidAt(new \DateTimeImmutable());
        $old->generateOrderNumber();
        $this->forceTotal($old, '5.00');
        $this->forceCreatedAt($old, new \DateTimeImmutable('2024-01-01 10:00:00'));

        $inRange = (new Purchase())->setUser($user)->setStatus('paid')->setPaidAt(new \DateTimeImmutable());
        $inRange->generateOrderNumber();
        $this->forceTotal($inRange, '7.00');
        $this->forceCreatedAt($inRange, new \DateTimeImmutable('2026-02-01 10:00:00'));

        $this->em->persist($old);
        $this->em->persist($inRange);
        $this->em->flush();

        $from = new \DateTimeImmutable('2026-01-01 00:00:00');
        $to   = new \DateTimeImmutable('2026-03-01 00:00:00');

        $results = $this->repo->findByUserAndPeriod($user, $from, $to);

        self::assertCount(1, $results);
        self::assertSame($inRange->getOrderNumber(), $results[0]->getOrderNumber());
    }

    public function testGetTotalOrdersAndSpentWithAndWithoutStatus(): void
    {
        $user = $this->getTestUser();

        $p1 = (new Purchase())->setUser($user)->setStatus('paid')->setPaidAt(new \DateTimeImmutable());
        $p1->generateOrderNumber();
        $this->forceTotal($p1, '10.00');

        $p2 = (new Purchase())->setUser($user)->setStatus('paid')->setPaidAt(new \DateTimeImmutable());
        $p2->generateOrderNumber();
        $this->forceTotal($p2, '20.00');

        $p3 = (new Purchase())->setUser($user)->setStatus('cart');
        $p3->generateOrderNumber();
        $this->forceTotal($p3, '99.00');

        $this->em->persist($p1);
        $this->em->persist($p2);
        $this->em->persist($p3);
        $this->em->flush();

        // sans filtre status
        $expectedTotal = 10.00 + 20.00 + 99.00;
        self::assertSame(3, $this->repo->getTotalOrders($user));
        self::assertEqualsWithDelta($expectedTotal, $this->repo->getTotalSpent($user), 0.0001);

        // avec filtre paid
        $expectedPaid = 10.00 + 20.00;
        self::assertSame(2, $this->repo->getTotalOrders($user, 'paid'));
        self::assertEqualsWithDelta($expectedPaid, $this->repo->getTotalSpent($user, 'paid'), 0.0001);
    }
}