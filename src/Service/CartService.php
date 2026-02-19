<?php

namespace App\Service;

use App\Repository\PurchaseRepository;
use Symfony\Component\Security\Core\Security;

class CartService
{
    private PurchaseRepository $purchaseRepo;
    private Security $security;

    public function __construct(PurchaseRepository $purchaseRepo, Security $security)
    {
        $this->purchaseRepo = $purchaseRepo;
        $this->security = $security;
    }

    public function getItemCount(): int
    {
        $user = $this->security->getUser();
        if (!$user) return 0;

        $cart = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => 'pending'
        ]);

        return $cart ? count($cart->getItems()) : 0;
    }
}

