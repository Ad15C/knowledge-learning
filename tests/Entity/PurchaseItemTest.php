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

        self::assertSame(1, $item->getQuantity());
        self::assertNull($item->getPurchase());
        self::assertNull($item->getLesson());
        self::assertNull($item->getCursus());
    }

    public function testSetAsLessonItem(): void
    {
        $item = new PurchaseItem();

        $purchase = $this->createMock(Purchase::class);
        $lesson = $this->createMock(Lesson::class);

        $item->setPurchase($purchase)
            ->setLesson($lesson)
            ->setQuantity(3)
            ->setUnitPrice(19.99);

        self::assertSame($purchase, $item->getPurchase());
        self::assertSame($lesson, $item->getLesson());
        self::assertNull($item->getCursus());
        self::assertSame(3, $item->getQuantity());
        self::assertEqualsWithDelta(19.99, $item->getUnitPrice(), 0.0001);
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
    }

   
    public function testGetTotal(): void
    {
        $item = new PurchaseItem();
        $item->setUnitPrice(12.50)->setQuantity(4);

        $this->assertEqualsWithDelta(50.0, $item->getTotal(), 0.0001);
    }
}