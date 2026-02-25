<?php

namespace App\Tests\Twig;

use App\Entity\Purchase;
use App\Twig\PurchaseItemsExtension;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class PurchaseItemsExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersItemsHelpers(): void
    {
        $ext = new PurchaseItemsExtension();
        $functions = $ext->getFunctions();

        self::assertCount(2, $functions);
        self::assertSame('purchase_items_count', $functions[0]->getName());
        self::assertSame('purchase_items_quantity', $functions[1]->getName());
    }

    public function testItemsCountReturnsCollectionCount(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getItems')
            ->willReturn(new ArrayCollection([new \stdClass(), new \stdClass(), new \stdClass()]));

        $ext = new PurchaseItemsExtension();

        self::assertSame(3, $ext->itemsCount($purchase));
    }

    public function testItemsQuantitySumsQuantities(): void
    {
        $item1 = new class {
            public function getQuantity(): int { return 2; }
        };
        $item2 = new class {
            public function getQuantity(): int { return 3; }
        };

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getItems')
            ->willReturn(new ArrayCollection([$item1, $item2]));

        $ext = new PurchaseItemsExtension();

        self::assertSame(5, $ext->itemsQuantity($purchase));
    }

    public function testItemsQuantityEmpty(): void
    {
        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getItems')
            ->willReturn(new ArrayCollection());

        $ext = new PurchaseItemsExtension();

        self::assertSame(0, $ext->itemsQuantity($purchase));
    }
}