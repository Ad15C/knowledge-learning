<?php

namespace App\Tests\Service;

use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Repository\PurchaseRepository;
use App\Service\CartService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class CartServiceTest extends TestCase
{
    public function testGetCartItemCountReturnsZeroWhenAnonymous(): void
    {
        $purchaseRepo = $this->createMock(PurchaseRepository::class);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $service = new CartService($purchaseRepo, $security);

        self::assertSame(0, $service->getCartItemCount());
    }

    public function testGetCartItemCountReturnsZeroWhenNoCart(): void
    {
        $user = $this->createMock(UserInterface::class);

        $purchaseRepo = $this->createMock(PurchaseRepository::class);
        $purchaseRepo->expects(self::once())
            ->method('findOneBy')
            ->with([
                'user' => $user,
                'status' => Purchase::STATUS_CART,
            ])
            ->willReturn(null);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $service = new CartService($purchaseRepo, $security);

        self::assertSame(0, $service->getCartItemCount());
    }

    public function testGetCartItemCountReturnsItemsCount(): void
    {
        $user = $this->createMock(UserInterface::class);

        $purchase = new Purchase();
        $purchase->setStatus(Purchase::STATUS_CART);

        $item1 = (new PurchaseItem())->setUnitPrice(10.0)->setQuantity(1);
        $item2 = (new PurchaseItem())->setUnitPrice(20.0)->setQuantity(1);

        $purchase->addItem($item1);
        $purchase->addItem($item2);

        $purchaseRepo = $this->createMock(PurchaseRepository::class);
        $purchaseRepo->expects(self::once())
            ->method('findOneBy')
            ->with([
                'user' => $user,
                'status' => Purchase::STATUS_CART,
            ])
            ->willReturn($purchase);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $service = new CartService($purchaseRepo, $security);

        self::assertSame(2, $service->getCartItemCount());
    }
}