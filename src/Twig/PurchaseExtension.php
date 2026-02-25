<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PurchaseExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('purchase_status_label', [$this, 'statusLabel']),
            new TwigFunction('purchase_status_class', [$this, 'statusClass']),
        ];
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'cart' => 'Panier',
            'paid' => 'Payée',
            'cancelled' => 'Annulée',
            'refunded' => 'Remboursée',
            default => 'Inconnu',
        };
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'cart' => 'badge badge-cart',
            'paid' => 'badge badge-paid',
            'cancelled' => 'badge badge-cancelled',
            'refunded' => 'badge badge-refunded',
            default => 'badge badge-unknown',
        };
    }
}