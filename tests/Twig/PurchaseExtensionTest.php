<?php

namespace App\Tests\Twig;

use App\Twig\PurchaseExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class PurchaseExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersStatusHelpers(): void
    {
        $extension = new PurchaseExtension();
        $functions = $extension->getFunctions();

        self::assertNotEmpty($functions);

        $byName = [];
        foreach ($functions as $function) {
            $byName[$function->getName()] = $function;
        }

        self::assertArrayHasKey('purchase_status_label', $byName);
        self::assertArrayHasKey('purchase_status_class', $byName);

        self::assertInstanceOf(TwigFunction::class, $byName['purchase_status_label']);
        self::assertInstanceOf(TwigFunction::class, $byName['purchase_status_class']);

        self::assertSame([$extension, 'statusLabel'], $byName['purchase_status_label']->getCallable());
        self::assertSame([$extension, 'statusClass'], $byName['purchase_status_class']->getCallable());
    }

    /**
     * @dataProvider statusLabelProvider
     */
    public function testStatusLabel(string $status, string $expected): void
    {
        $extension = new PurchaseExtension();

        self::assertSame($expected, $extension->statusLabel($status));
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public static function statusLabelProvider(): array
    {
        return [
            'cart' => ['cart', 'Panier'],
            'pending' => ['pending', 'En attente'],
            'paid' => ['paid', 'Payée'],
            'canceled' => ['canceled', 'Annulée'],
            'unknown' => ['something_else', 'Inconnu'],
        ];
    }

    /**
     * @dataProvider statusClassProvider
     */
    public function testStatusClass(string $status, string $expected): void
    {
        $extension = new PurchaseExtension();

        self::assertSame($expected, $extension->statusClass($status));
    }

    /**
     * @return array<string, array{0:string,1:string}>
     */
    public static function statusClassProvider(): array
    {
        return [
            'cart' => ['cart', 'badge badge-cart'],
            'pending' => ['pending', 'badge badge-pending'],
            'paid' => ['paid', 'badge badge-paid'],
            'canceled' => ['canceled', 'badge badge-canceled'],
            'unknown' => ['something_else', 'badge badge-unknown'],
        ];
    }

    public function testTwigFunctionCallablesExecute(): void
    {
        $extension = new PurchaseExtension();

        $byName = [];
        foreach ($extension->getFunctions() as $function) {
            $byName[$function->getName()] = $function;
        }

        $labelCallable = $byName['purchase_status_label']->getCallable();
        $classCallable = $byName['purchase_status_class']->getCallable();

        self::assertIsCallable($labelCallable);
        self::assertIsCallable($classCallable);

        self::assertSame('Panier', $labelCallable('cart'));
        self::assertSame('badge badge-paid', $classCallable('paid'));
    }
}