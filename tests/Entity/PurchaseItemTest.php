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

        $this->assertSame(1, $item->getQuantity());
        $this->assertNull($item->getPurchase());
        $this->assertNull($item->getLesson());
        $this->assertNull($item->getCursus());
    }

    public function testSettersAndGetters(): void
    {
        $item = new PurchaseItem();

        $purchase = $this->createMock(Purchase::class);
        $lesson = $this->createMock(Lesson::class);
        $cursus = $this->createMock(Cursus::class);

        $item->setPurchase($purchase)
            ->setLesson($lesson)
            ->setCursus($cursus)
            ->setQuantity(3)
            ->setUnitPrice(19.99);

        $this->assertSame($purchase, $item->getPurchase());
        $this->assertSame($lesson, $item->getLesson());
        $this->assertSame($cursus, $item->getCursus());
        $this->assertSame(3, $item->getQuantity());
        $this->assertSame(19.99, $item->getUnitPrice());
    }

    public function testGetTotal(): void
    {
        $item = new PurchaseItem();
        $item->setUnitPrice(12.50)->setQuantity(4);

        $this->assertSame(50.0, $item->getTotal());
    }
}