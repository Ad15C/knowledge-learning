<?php

namespace App\Tests\Controller\User;

use App\Entity\Purchase;

class PurchasesTest extends AbstractUserWebTestCase
{
    private function createPurchase(string $status, string $createdAt = '2026-02-10 10:00:00'): Purchase
    {
        $purchase = new Purchase();
        $purchase->setUser($this->getFixtureUser());
        $purchase->setStatus($status);

        // createdAt est set dans le constructeur => on ne peut pas le setter sans méthode.
        // Donc on ne le change pas ici. On teste surtout le filtre status.
        // (Si tu veux tester from/to strict, il faut ajouter un setter ou utiliser Reflection en test.)

        if ($status === 'paid') {
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

        $this->assertSelectorExists('form.filter-form');
        $this->assertSelectorExists('select[name="status"]');
        $this->assertSelectorExists('input[type="date"][name="from"]');
        $this->assertSelectorExists('input[type="date"][name="to"]');
        $this->assertSelectorExists('button[type="submit"]');

        $this->assertSelectorExists('p.cart-empty');
        $this->assertSelectorTextContains('p.cart-empty', "Vous n'avez aucun achat");

        $this->assertSelectorExists('a.btn-back[href="/dashboard"]');
    }

    public function testPurchasesListDisplaysCreatedPurchases(): void
    {
        $client = $this->client;
        $client->loginUser($this->getFixtureUser());

        $p1 = $this->createPurchase('paid');
        $p2 = $this->createPurchase('pending');

        $client->request('GET', '/dashboard/purchases');
        $this->assertResponseIsSuccessful();

        // On ne doit plus voir le message vide
        $this->assertSelectorNotExists('p.cart-empty');

        // Vérifie que les commandes apparaissent (Commande #ID)
        $this->assertSelectorTextContains('.cart-items', 'Commande #' . $p1->getId());
        $this->assertSelectorTextContains('.cart-items', 'Commande #' . $p2->getId());

        // Vérifie que le statut est affiché
        $this->assertSelectorTextContains('.cart-items', 'Statut');
    }

    public function testPurchasesStatusFilterShowsOnlyPaid(): void
    {
        $client = $this->client;
        $client->loginUser($this->getFixtureUser());

        $paid = $this->createPurchase('paid');
        $pending = $this->createPurchase('pending');

        $client->request('GET', '/dashboard/purchases?status=paid');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('.cart-items', 'Commande #' . $paid->getId());
        $this->assertSelectorTextNotContains('body', 'Commande #' . $pending->getId());

        // option selected
        $this->assertSelectorExists('select[name="status"] option[value="paid"][selected]');
    }
}