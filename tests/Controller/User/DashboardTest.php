<?php

namespace App\Tests\Controller\User;

use App\Entity\Purchase;
use App\Entity\User;

class DashboardTest extends AbstractUserWebTestCase
{
    private function createPurchaseWithTotal(User $user, string $status, string $total): Purchase
    {
        $purchase = new Purchase();
        $purchase->setUser($user);
        $purchase->setStatus($status);

        $ref = new \ReflectionClass(Purchase::class);

        // orderNumber obligatoire (NOT NULL + unique)
        $propOrderNumber = $ref->getProperty('orderNumber');
        $propOrderNumber->setAccessible(true);
        $propOrderNumber->setValue(
            $purchase,
            'ORD-TEST-' . date('YmdHis') . '-' . bin2hex(random_bytes(4))
        );

        // total est stocké en string (decimal)
        $propTotal = $ref->getProperty('total');
        $propTotal->setAccessible(true);
        $propTotal->setValue($purchase, $total);

        $this->em->persist($purchase);
        $this->em->flush();

        return $purchase;
    }

    public function testDashboardShowsBronzeWhenTotalSpentBelow100(): void
    {
        $client = $this->client;
        $user = $this->getFixtureUser();

        // 99 => Bronze, next Silver
        $this->createPurchaseWithTotal($user, Purchase::STATUS_PAID, '99.00');

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.dashboard-card', 'Bronze');
        $this->assertSelectorTextContains('.dashboard-card', 'Silver');

        // si ta vue affiche le total
        $this->assertSelectorTextContains('body', '99');
    }

    public function testDashboardShowsSilverWhenTotalSpentAtLeast100(): void
    {
        $client = $this->client;
        $user = $this->getFixtureUser();

        // 120 => Silver, next Gold
        $this->createPurchaseWithTotal($user, Purchase::STATUS_PAID, '120.00');

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.dashboard-card', 'Silver');
        $this->assertSelectorTextContains('.dashboard-card', 'Gold');
    }

    public function testDashboardShowsGoldWhenTotalSpentAtLeast300(): void
    {
        $client = $this->client;
        $user = $this->getFixtureUser();

        // 350 => Gold, next Platinum
        $this->createPurchaseWithTotal($user, Purchase::STATUS_PAID, '350.00');

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.dashboard-card', 'Gold');
        $this->assertSelectorTextContains('.dashboard-card', 'Platinum');
    }
}