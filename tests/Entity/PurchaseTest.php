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

        $this->assertSame('cart', $purchase->getStatus());
        $this->assertSame(0.0, $purchase->getTotal());
        $this->assertInstanceOf(\DateTimeImmutable::class, $purchase->getCreatedAt());
        $this->assertNull($purchase->getPaidAt());
        $this->assertCount(0, $purchase->getItems());
    }

    public function testSetUser(): void
    {
        $purchase = new Purchase();
        $user = $this->createMock(User::class);

        $purchase->setUser($user);

        $this->assertSame($user, $purchase->getUser());
    }

    public function testSetStatus(): void
    {
        $purchase = new Purchase();

        $purchase->setStatus('paid');

        $this->assertSame('paid', $purchase->getStatus());
    }

    public function testSetPaidAt(): void
    {
        $purchase = new Purchase();
        $dt = new \DateTimeImmutable('2026-02-24 10:00:00');

        $purchase->setPaidAt($dt);

        $this->assertSame($dt, $purchase->getPaidAt());
    }

    public function testGenerateOrderNumberIfMissing(): void
    {
        $purchase = new Purchase();

        $purchase->generateOrderNumber();

        $this->assertNotNull($purchase->getOrderNumber());
        $this->assertMatchesRegularExpression('/^ORD-\d{8}-[a-f0-9]{8}$/', $purchase->getOrderNumber());
    }

    public function testGenerateOrderNumberDoesNotOverrideExisting(): void
    {
        $purchase = new Purchase();

        // on force la propriété privée via reflection (pas de setter)
        $ref = new \ReflectionClass($purchase);
        $prop = $ref->getProperty('orderNumber');
        $prop->setAccessible(true);
        $prop->setValue($purchase, 'ORD-20260101-deadbeef');

        $purchase->generateOrderNumber();

        $this->assertSame('ORD-20260101-deadbeef', $purchase->getOrderNumber());
    }

    public function testAddItemSetsOwningSide(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();

        // PurchaseItem::setUnitPrice est typé float, on le met pour cohérence
        $item->setUnitPrice(10.0)->setQuantity(2);

        $purchase->addItem($item);

        $this->assertCount(1, $purchase->getItems());
        $this->assertSame($purchase, $item->getPurchase());
    }

    public function testAddItemTwiceDoesNotDuplicate(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();
        $item->setUnitPrice(10.0)->setQuantity(1);

        $purchase->addItem($item);
        $purchase->addItem($item);

        $this->assertCount(1, $purchase->getItems());
    }

    public function testRemoveItemUnsetsOwningSide(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();
        $item->setUnitPrice(10.0)->setQuantity(1);

        $purchase->addItem($item);
        $purchase->removeItem($item);

        $this->assertCount(0, $purchase->getItems());
        $this->assertNull($item->getPurchase());
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

        $this->assertSame(35.20, $purchase->getTotal());
    }
}