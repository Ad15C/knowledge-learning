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

    public function getCartItemCount(): int
    {
        $user = $this->security->getUser();
        if (!$user) return 0;

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => 'cart'
        ]);

        return $purchase ? count($purchase->getItems()) : 0;
    }
}
