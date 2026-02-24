<?php

namespace App\Tests\Service;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CartServiceIntegrationTest extends WebTestCase
{
    public function testGetCartItemCountWithRealDb(): void
    {
        $client = static::createClient();

        $container = static::getContainer();
        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);

        $em = $container->get(EntityManagerInterface::class);
        $cartService = $container->get(CartService::class);

        $user = $em->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        $lesson = $em->getRepository(\App\Entity\Lesson::class)
            ->findOneBy(['title' => 'Découverte de l’instrument']);

        $client->loginUser($user);

        $purchase = (new Purchase())->setUser($user)->setStatus('cart');
        $purchase->generateOrderNumber();
        $em->persist($purchase);

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setLesson($lesson)
            ->setUnitPrice($lesson->getPrice())
            ->setQuantity(1);

        $em->persist($item);
        $purchase->addItem($item);
        $purchase->calculateTotal();
        $em->flush();

        self::assertSame(1, $cartService->getCartItemCount());
    }
}