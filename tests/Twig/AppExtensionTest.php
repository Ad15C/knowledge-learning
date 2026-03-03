<?php

namespace App\Tests\Twig;

use App\Service\CartService;
use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

class AppExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersCartItemCount(): void
    {
        $cartService = $this->createMock(CartService::class);
        $extension = new AppExtension($cartService);

        $functions = $extension->getFunctions();

        self::assertNotEmpty($functions);

        $function = null;
        foreach ($functions as $fn) {
            if ($fn->getName() === 'cart_item_count') {
                $function = $fn;
                break;
            }
        }

        self::assertInstanceOf(TwigFunction::class, $function);
        self::assertSame('cart_item_count', $function->getName());
        self::assertSame([$extension, 'getCartItemCount'], $function->getCallable());
    }

    public function testGetCartItemCountReturnsServiceValue(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cartService->expects(self::once())
            ->method('getCartItemCount')
            ->willReturn(5);

        $extension = new AppExtension($cartService);

        self::assertSame(5, $extension->getCartItemCount());
    }

    public function testTwigFunctionCallableExecutesService(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cartService->expects(self::once())
            ->method('getCartItemCount')
            ->willReturn(3);

        $extension = new AppExtension($cartService);

        $functions = $extension->getFunctions();

        $function = null;
        foreach ($functions as $fn) {
            if ($fn->getName() === 'cart_item_count') {
                $function = $fn;
                break;
            }
        }

        self::assertNotNull($function);

        $callable = $function->getCallable();

        self::assertSame(3, $callable());
    }
}