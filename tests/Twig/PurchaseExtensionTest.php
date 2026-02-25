<?php

namespace App\Tests\Twig;

use App\Twig\PurchaseExtension;
use PHPUnit\Framework\TestCase;

class PurchaseExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersStatusHelpers(): void
    {
        $ext = new PurchaseExtension();
        $functions = $ext->getFunctions();

        self::assertCount(2, $functions);
        self::assertSame('purchase_status_label', $functions[0]->getName());
        self::assertSame('purchase_status_class', $functions[1]->getName());
    }

    public function testStatusLabel(): void
    {
        $ext = new PurchaseExtension();

        self::assertSame('Panier', $ext->statusLabel('cart'));
        self::assertSame('Payée', $ext->statusLabel('paid'));
        self::assertSame('Annulée', $ext->statusLabel('cancelled'));
        self::assertSame('Remboursée', $ext->statusLabel('refunded'));
        self::assertSame('Inconnu', $ext->statusLabel('something_else'));
    }

    public function testStatusClass(): void
    {
        $ext = new PurchaseExtension();

        self::assertSame('badge badge-cart', $ext->statusClass('cart'));
        self::assertSame('badge badge-paid', $ext->statusClass('paid'));
        self::assertSame('badge badge-cancelled', $ext->statusClass('cancelled'));
        self::assertSame('badge badge-refunded', $ext->statusClass('refunded'));
        self::assertSame('badge badge-unknown', $ext->statusClass('something_else'));
    }
}