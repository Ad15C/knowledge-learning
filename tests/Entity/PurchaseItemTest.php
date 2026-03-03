<?php

namespace App\Tests\Entity;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use PHPUnit\Framework\TestCase;

class PurchaseItemTest extends TestCase
{
    public function testDefaults(): void
    {
        $item = new PurchaseItem();

        self::assertNull($item->getId());
        self::assertNull($item->getPurchase());
        self::assertNull($item->getLesson());
        self::assertNull($item->getCursus());

        self::assertSame(1, $item->getQuantity());

        // Dans ton entité, unitPrice a un défaut '0.00' => OK
        self::assertEqualsWithDelta(0.00, $item->getUnitPrice(), 0.0001);

        // Total = unitPrice * quantity
        self::assertEqualsWithDelta(0.00, $item->getTotal(), 0.0001);
    }

    public function testSetAsLessonItem(): void
    {
        $item = new PurchaseItem();

        $purchase = $this->createMock(Purchase::class);
        $lesson = $this->createMock(Lesson::class);

        self::assertSame($item, $item->setPurchase($purchase));
        self::assertSame($item, $item->setLesson($lesson));
        self::assertSame($item, $item->setQuantity(3));
        self::assertSame($item, $item->setUnitPrice(19.99));

        self::assertSame($purchase, $item->getPurchase());
        self::assertSame($lesson, $item->getLesson());
        self::assertNull($item->getCursus());

        self::assertSame(3, $item->getQuantity());
        self::assertEqualsWithDelta(19.99, $item->getUnitPrice(), 0.0001);
        self::assertEqualsWithDelta(59.97, $item->getTotal(), 0.0001);
    }

    public function testSetAsCursusItem(): void
    {
        $item = new PurchaseItem();

        $purchase = $this->createMock(Purchase::class);
        $cursus = $this->createMock(Cursus::class);

        $item->setPurchase($purchase)
            ->setCursus($cursus)
            ->setQuantity(1)
            ->setUnitPrice(50.00);

        self::assertSame($purchase, $item->getPurchase());
        self::assertSame($cursus, $item->getCursus());
        self::assertNull($item->getLesson());

        self::assertSame(1, $item->getQuantity());
        self::assertEqualsWithDelta(50.00, $item->getUnitPrice(), 0.0001);
        self::assertEqualsWithDelta(50.00, $item->getTotal(), 0.0001);
    }

    public function testQuantityIsClampedToMinimumOne(): void
    {
        $item = new PurchaseItem();

        $item->setQuantity(0);
        self::assertSame(1, $item->getQuantity());

        $item->setQuantity(-10);
        self::assertSame(1, $item->getQuantity());

        $item->setQuantity(2);
        self::assertSame(2, $item->getQuantity());
    }

    public function testUnitPriceIsFormattedToTwoDecimals(): void
    {
        $item = new PurchaseItem();

        $item->setUnitPrice(10);
        self::assertEqualsWithDelta(10.00, $item->getUnitPrice(), 0.0001);

        $item->setUnitPrice(10.5);
        self::assertEqualsWithDelta(10.50, $item->getUnitPrice(), 0.0001);

        $item->setUnitPrice(10.999);
        self::assertEqualsWithDelta(11.00, $item->getUnitPrice(), 0.0001);
    }

    public function testGetTotal(): void
    {
        $item = new PurchaseItem();
        $item->setUnitPrice(12.50)->setQuantity(4);

        self::assertEqualsWithDelta(50.00, $item->getTotal(), 0.0001);
    }
}