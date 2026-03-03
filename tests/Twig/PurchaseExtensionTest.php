<?php

namespace App\Tests\Twig;

use App\Twig\PurchaseExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class PurchaseExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersStatusHelpers(): void
    {
        $ext = new PurchaseExtension();
        $functions = $ext->getFunctions();

        self::assertNotEmpty($functions);

        $byName = [];
        foreach ($functions as $fn) {
            $byName[$fn->getName()] = $fn;
        }

        self::assertArrayHasKey('purchase_status_label', $byName);
        self::assertArrayHasKey('purchase_status_class', $byName);

        self::assertInstanceOf(TwigFunction::class, $byName['purchase_status_label']);
        self::assertInstanceOf(TwigFunction::class, $byName['purchase_status_class']);

        self::assertSame([$ext, 'statusLabel'], $byName['purchase_status_label']->getCallable());
        self::assertSame([$ext, 'statusClass'], $byName['purchase_status_class']->getCallable());
    }

    /**
     * @dataProvider statusLabelProvider
     */
    public function testStatusLabel(string $status, string $expected): void
    {
        $ext = new PurchaseExtension();
        self::assertSame($expected, $ext->statusLabel($status));
    }

    public static function statusLabelProvider(): array
    {
        return [
            ['cart', 'Panier'],
            ['paid', 'Payée'],
            ['cancelled', 'Annulée'],
            ['refunded', 'Remboursée'],
            ['something_else', 'Inconnu'],
        ];
    }

    /**
     * @dataProvider statusClassProvider
     */
    public function testStatusClass(string $status, string $expected): void
    {
        $ext = new PurchaseExtension();
        self::assertSame($expected, $ext->statusClass($status));
    }

    public static function statusClassProvider(): array
    {
        return [
            ['cart', 'badge badge-cart'],
            ['paid', 'badge badge-paid'],
            ['cancelled', 'badge badge-cancelled'],
            ['refunded', 'badge badge-refunded'],
            ['something_else', 'badge badge-unknown'],
        ];
    }

    public function testTwigFunctionCallablesExecute(): void
    {
        $ext = new PurchaseExtension();

        $byName = [];
        foreach ($ext->getFunctions() as $fn) {
            $byName[$fn->getName()] = $fn;
        }

        $labelCallable = $byName['purchase_status_label']->getCallable();
        $classCallable = $byName['purchase_status_class']->getCallable();

        self::assertSame('Panier', $labelCallable('cart'));
        self::assertSame('badge badge-paid', $classCallable('paid'));
    }
}