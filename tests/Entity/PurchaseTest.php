<?php

namespace App\Tests\Entity;

use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PurchaseTest extends TestCase
{
    public function testDefaultsOnConstruct(): void
    {
        $purchase = new Purchase();

        self::assertNull($purchase->getId());
        self::assertSame(Purchase::STATUS_CART, $purchase->getStatus());

        // total est stocké en decimal string => float cast
        self::assertEqualsWithDelta(0.00, $purchase->getTotal(), 0.0001);

        self::assertInstanceOf(\DateTimeImmutable::class, $purchase->getCreatedAt());
        self::assertNull($purchase->getPaidAt());

        self::assertCount(0, $purchase->getItems());
        self::assertNull($purchase->getOrderNumber()); // avant persist / prePersist
    }

    public function testSetUser(): void
    {
        $purchase = new Purchase();
        $user = $this->createMock(User::class);

        self::assertSame($purchase, $purchase->setUser($user));
        self::assertSame($user, $purchase->getUser());
    }

    public function testSetStatus(): void
    {
        $purchase = new Purchase();

        $purchase->setStatus(Purchase::STATUS_PAID);
        self::assertSame(Purchase::STATUS_PAID, $purchase->getStatus());
    }

    public function testSetStatusThrowsOnInvalidValue(): void
    {
        $purchase = new Purchase();

        $this->expectException(\InvalidArgumentException::class);
        $purchase->setStatus('invalid-status');
    }

    public function testSetPaidAt(): void
    {
        $purchase = new Purchase();
        $dt = new \DateTimeImmutable('2026-02-24 10:00:00');

        self::assertSame($purchase, $purchase->setPaidAt($dt));
        self::assertSame($dt, $purchase->getPaidAt());
    }

    public function testGenerateOrderNumberIfMissing(): void
    {
        $purchase = new Purchase();

        $purchase->generateOrderNumber();

        self::assertNotNull($purchase->getOrderNumber());
        self::assertMatchesRegularExpression('/^ORD-\d{8}-[a-f0-9]{8}$/', $purchase->getOrderNumber());
    }

    public function testGenerateOrderNumberDoesNotOverrideExisting(): void
    {
        $purchase = new Purchase();

        $ref = new \ReflectionClass($purchase);
        $prop = $ref->getProperty('orderNumber');
        $prop->setAccessible(true);
        $prop->setValue($purchase, 'ORD-20260101-deadbeef');

        $purchase->generateOrderNumber();

        self::assertSame('ORD-20260101-deadbeef', $purchase->getOrderNumber());
    }

    public function testAddItemSetsOwningSide(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();

        $item->setUnitPrice(10.0)->setQuantity(2);

        $purchase->addItem($item);

        self::assertCount(1, $purchase->getItems());
        self::assertSame($purchase, $item->getPurchase());
    }

    public function testAddItemTwiceDoesNotDuplicate(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();
        $item->setUnitPrice(10.0)->setQuantity(1);

        $purchase->addItem($item);
        $purchase->addItem($item);

        self::assertCount(1, $purchase->getItems());
    }

    public function testRemoveItemUnsetsOwningSide(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();
        $item->setUnitPrice(10.0)->setQuantity(1);

        $purchase->addItem($item);
        $purchase->removeItem($item);

        self::assertCount(0, $purchase->getItems());
        self::assertNull($item->getPurchase());
    }

    public function testCalculateTotal(): void
    {
        $purchase = new Purchase();

        $item1 = new PurchaseItem();
        $item1->setUnitPrice(12.50)->setQuantity(2); // 25.00

        $item2 = new PurchaseItem();
        $item2->setUnitPrice(3.40)->setQuantity(3); // 10.20

        $purchase->addItem($item1);
        $purchase->addItem($item2);

        $purchase->calculateTotal();

        self::assertEqualsWithDelta(35.20, $purchase->getTotal(), 0.0001);
    }

    public function testCalculateTotalRoundsToTwoDecimals(): void
    {
        $purchase = new Purchase();

        // PurchaseItem::setUnitPrice() formatte à 2 décimales => 10.01
        $item = new PurchaseItem();
        $item->setUnitPrice(10.005)->setQuantity(1);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        self::assertEqualsWithDelta(10.01, $purchase->getTotal(), 0.0001);
    }

    public function testCalculateTotalWithNoItems(): void
    {
        $purchase = new Purchase();

        $purchase->calculateTotal();

        self::assertEqualsWithDelta(0.00, $purchase->getTotal(), 0.0001);
    }

    public function testMarkPaidSetsStatusAndPaidAt(): void
    {
        $purchase = new Purchase();

        self::assertSame($purchase, $purchase->markPaid());
        self::assertSame(Purchase::STATUS_PAID, $purchase->getStatus());
        self::assertInstanceOf(\DateTimeImmutable::class, $purchase->getPaidAt());
    }

    public function testMarkPendingSetsStatus(): void
    {
        $purchase = new Purchase();

        self::assertSame($purchase, $purchase->markPending());
        self::assertSame(Purchase::STATUS_PENDING, $purchase->getStatus());
    }

    public function testMarkCanceledSetsStatus(): void
    {
        $purchase = new Purchase();

        self::assertSame($purchase, $purchase->markCanceled());
        self::assertSame(Purchase::STATUS_CANCELED, $purchase->getStatus());
    }

    public function testStatusHelpers(): void
    {
        $p = new Purchase();

        self::assertTrue($p->isCart());
        self::assertFalse($p->isPaid());

        $p->setStatus(Purchase::STATUS_PAID);
        self::assertTrue($p->isPaid());
        self::assertFalse($p->isCart());
    }

    public function testGetStatusLabel(): void
    {
        $p = new Purchase();

        $p->setStatus(Purchase::STATUS_CART);
        self::assertSame('Panier', $p->getStatusLabel());

        $p->setStatus(Purchase::STATUS_PENDING);
        self::assertSame('En attente', $p->getStatusLabel());

        $p->setStatus(Purchase::STATUS_PAID);
        self::assertSame('Payée', $p->getStatusLabel());

        $p->setStatus(Purchase::STATUS_CANCELED);
        self::assertSame('Annulée', $p->getStatusLabel());
    }
}