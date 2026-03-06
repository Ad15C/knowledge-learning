<?php

namespace App\Service;

use App\Entity\Purchase;
use App\Repository\PurchaseRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class CartService
{
    public function __construct(
        private PurchaseRepository $purchaseRepo,
        private TokenStorageInterface $tokenStorage
    ) {}

    public function getCartItemCount(): int
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if (!$user instanceof UserInterface) {
            return 0;
        }

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => Purchase::STATUS_CART,
        ]);

        return $purchase ? $purchase->getItems()->count() : 0;
    }
}