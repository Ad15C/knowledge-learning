<?php

namespace App\Service;

use App\Entity\Purchase;
use App\Repository\PurchaseRepository;
use Symfony\Component\Security\Core\Security;

class CartService
{
    public function __construct(
        private PurchaseRepository $purchaseRepo,
        private Security $security
    ) {}

    public function getCartItemCount(): int
    {
        $user = $this->security->getUser();
        if (!$user) {
            return 0;
        }

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => Purchase::STATUS_CART,
        ]);

        return $purchase ? $purchase->getItems()->count() : 0;
    }
}