<?php

namespace App\Tests\Twig;

use App\Entity\Purchase;
use App\Twig\PurchaseItemsExtension;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class PurchaseItemsExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersItemsHelpers(): void
    {
        $ext = new PurchaseItemsExtension();
        $functions = $ext->getFunctions();

        self::assertNotEmpty($functions);

        $byName = [];
        foreach ($functions as $fn) {
            $byName[$fn->getName()] = $fn;
        }

        self::assertArrayHasKey('purchase_items_count', $byName);
        self::assertArrayHasKey('purchase_items_quantity', $byName);

        self::assertInstanceOf(TwigFunction::class, $byName['purchase_items_count']);
        self::assertInstanceOf(TwigFunction::class, $byName['purchase_items_quantity']);

        self::assertSame([$ext, 'itemsCount'], $byName['purchase_items_count']->getCallable());
        self::assertSame([$ext, 'itemsQuantity'], $byName['purchase_items_quantity']->getCallable());
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

    public function testTwigFunctionCallablesExecute(): void
    {
        $item1 = new class {
            public function getQuantity(): int { return 2; }
        };
        $item2 = new class {
            public function getQuantity(): int { return 1; }
        };

        $purchase = $this->createMock(Purchase::class);
        $purchase->method('getItems')
            ->willReturn(new ArrayCollection([$item1, $item2]));

        $ext = new PurchaseItemsExtension();

        $byName = [];
        foreach ($ext->getFunctions() as $fn) {
            $byName[$fn->getName()] = $fn;
        }

        $countCallable = $byName['purchase_items_count']->getCallable();
        $qtyCallable = $byName['purchase_items_quantity']->getCallable();

        self::assertSame(2, $countCallable($purchase));
        self::assertSame(3, $qtyCallable($purchase));
    }
}