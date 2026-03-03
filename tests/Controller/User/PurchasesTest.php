<?php

namespace App\Tests\Controller\User;

use App\Entity\Purchase;

class PurchasesTest extends AbstractUserWebTestCase
{
    private function createPurchase(string $status): Purchase
    {
        $purchase = new Purchase();
        $purchase->setUser($this->getFixtureUser());
        $purchase->setStatus($status);

        $ref = new \ReflectionClass(Purchase::class);

        // orderNumber obligatoire (NOT NULL + unique)
        $propOrderNumber = $ref->getProperty('orderNumber');
        $propOrderNumber->setAccessible(true);
        $propOrderNumber->setValue(
            $purchase,
            'ORD-TEST-' . date('YmdHis') . '-' . bin2hex(random_bytes(4))
        );

        if ($status === Purchase::STATUS_PAID) {
            $purchase->setPaidAt(new \DateTimeImmutable('2026-02-10 11:00:00'));
        }

        $this->em->persist($purchase);
        $this->em->flush();

        return $purchase;
    }

    public function testPurchasesPageLoadsEvenWhenEmpty(): void
    {
        $client = $this->client;
        $client->loginUser($this->getFixtureUser());

        $client->request('GET', '/dashboard/purchases');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Mes Achats');

        // Le form a la classe dashboard-filters (d'après ton Twig)
        $this->assertSelectorExists('form.dashboard-filters');
        $this->assertSelectorExists('select[name="status"]');
        $this->assertSelectorExists('input[type="date"][name="from"]');
        $this->assertSelectorExists('input[type="date"][name="to"]');
        $this->assertSelectorExists('button[type="submit"]');

        $this->assertSelectorExists('p.cart-empty');
        $this->assertSelectorTextContains('p.cart-empty', "Vous n'avez aucun achat");

        // Ton twig: class="btn-back" et lien route user_dashboard => /dashboard
        $this->assertSelectorExists('a.btn-back[href="/dashboard"]');
    }

    public function testPurchasesListDisplaysCreatedPurchases(): void
    {
        $client = $this->client;
        $client->loginUser($this->getFixtureUser());

        $p1 = $this->createPurchase(Purchase::STATUS_PAID);
        $p2 = $this->createPurchase(Purchase::STATUS_PENDING);

        $client->request('GET', '/dashboard/purchases');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorNotExists('p.cart-empty');

        // Ton twig affiche "Commande #{{ purchase.id }}"
        $this->assertSelectorTextContains('.cart-items', 'Commande #' . $p1->getId());
        $this->assertSelectorTextContains('.cart-items', 'Commande #' . $p2->getId());

        $this->assertSelectorTextContains('.cart-items', 'Statut');
    }

    public function testPurchasesStatusFilterShowsOnlyPaid(): void
    {
        $client = $this->client;
        $client->loginUser($this->getFixtureUser());

        $paid = $this->createPurchase(Purchase::STATUS_PAID);
        $pending = $this->createPurchase(Purchase::STATUS_PENDING);

        $client->request('GET', '/dashboard/purchases?status=paid');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('.cart-items', 'Commande #' . $paid->getId());
        $this->assertSelectorTextNotContains('body', 'Commande #' . $pending->getId());

        // Ton twig met selected si filter_status == 'paid'
        $this->assertSelectorExists('select[name="status"] option[value="paid"][selected]');
    }
}