<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use App\Service\CartService;

class AppExtension extends AbstractExtension
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cart_item_count', [$this, 'getCartItemCount']),
        ];
    }

    public function getCartItemCount(): int
    {
        return $this->cartService->getItemCount();
    }
}
