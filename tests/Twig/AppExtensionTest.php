<?php

namespace App\Tests\Twig;

use App\Service\CartService;
use App\Twig\AppExtension;
use PHPUnit\Framework\TestCase;

class AppExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersCartItemCount(): void
    {
        $cartService = $this->createMock(CartService::class);
        $ext = new AppExtension($cartService);

        $functions = $ext->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('cart_item_count', $functions[0]->getName());
        self::assertSame([$ext, 'getCartItemCount'], $functions[0]->getCallable());
    }

    public function testGetCartItemCountReturnsServiceValue(): void
    {
        $cartService = $this->createMock(CartService::class);
        $cartService->expects(self::once())
            ->method('getCartItemCount')
            ->willReturn(5);

        $ext = new AppExtension($cartService);

        self::assertSame(5, $ext->getCartItemCount());
    }
}