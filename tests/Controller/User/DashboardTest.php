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

        // set private string $total via Reflection
        $ref = new \ReflectionClass(Purchase::class);
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

        // TotalSpent = 99.00 => Bronze, next Silver
        $this->createPurchaseWithTotal($user, 'paid', '99.00');

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.dashboard-card', 'Statut actuel :');
        $this->assertSelectorTextContains('.dashboard-card', 'Bronze');
        $this->assertSelectorTextContains('.dashboard-card', 'vers Silver');

        // Total affiché dans la carte commandes
        $this->assertSelectorTextContains('.dashboard-cards', '99');
    }

    public function testDashboardShowsSilverWhenTotalSpentAtLeast100(): void
    {
        $client = $this->client;
        $user = $this->getFixtureUser();

        // TotalSpent = 120.00 => Silver, next Gold
        $this->createPurchaseWithTotal($user, 'paid', '120.00');

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.dashboard-card', 'Silver');
        $this->assertSelectorTextContains('.dashboard-card', 'vers Gold');
    }

    public function testDashboardShowsGoldWhenTotalSpentAtLeast300(): void
    {
        $client = $this->client;
        $user = $this->getFixtureUser();

        // TotalSpent = 350.00 => Gold, next Platinum
        $this->createPurchaseWithTotal($user, 'paid', '350.00');

        $client->loginUser($user);
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.dashboard-card', 'Gold');
        $this->assertSelectorTextContains('.dashboard-card', 'vers Platinum');
    }
}