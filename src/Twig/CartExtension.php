<?php

namespace App\Twig;

use App\Service\CartService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class CartExtension extends AbstractExtension implements GlobalsInterface
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function getGlobals(): array
    {
        // Variable disponible dans tous les templates Twig
        return [
            'cartCount' => $this->cartService->getCartItemCount(),
        ];
    }
}
