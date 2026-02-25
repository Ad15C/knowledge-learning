<?php

namespace App\Twig;

use App\Entity\Purchase;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PurchaseItemsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('purchase_items_count', [$this, 'itemsCount']),
            new TwigFunction('purchase_items_quantity', [$this, 'itemsQuantity']),
        ];
    }

    public function itemsCount(Purchase $purchase): int
    {
        return $purchase->getItems()->count();
    }

    public function itemsQuantity(Purchase $purchase): int
    {
        $qty = 0;
        foreach ($purchase->getItems() as $item) {
            $qty += $item->getQuantity();
        }
        return $qty;
    }
}