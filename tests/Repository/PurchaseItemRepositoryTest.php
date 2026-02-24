<?php

namespace App\Tests\Repository;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Repository\PurchaseItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PurchaseItemRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PurchaseItemRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();

        // Besoin de User + Lessons/Cursus depuis ThemeFixtures
        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(PurchaseItemRepository::class);
    }

    private function getTestUser(): \App\Entity\User
    {
        $user = $this->em->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user);

        return $user;
    }

    private function getGuitarCursus(): \App\Entity\Cursus
    {
        $cursus = $this->em->getRepository(\App\Entity\Cursus::class)
            ->findOneBy(['name' => 'Cursus d’initiation à la guitare']);

        self::assertNotNull($cursus);

        return $cursus;
    }

    private function getOneLesson(): \App\Entity\Lesson
    {
        $lesson = $this->em->getRepository(\App\Entity\Lesson::class)
            ->findOneBy(['title' => 'Découverte de l’instrument']);

        self::assertNotNull($lesson);

        return $lesson;
    }

    private function forceCreatedAt(Purchase $purchase, \DateTimeImmutable $dt): void
    {
        // createdAt private sans setter => reflection
        $ref = new \ReflectionClass($purchase);
        $prop = $ref->getProperty('createdAt');
        $prop->setAccessible(true);
        $prop->setValue($purchase, $dt);
    }

    public function testFindByPurchase(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $purchase = (new Purchase())->setUser($user)->setStatus('cart');
        $purchase->generateOrderNumber();
        $this->em->persist($purchase);

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setLesson($lesson)
            ->setUnitPrice($lesson->getPrice())
            ->setQuantity(1);

        $this->em->persist($item);
        $purchase->addItem($item);
        $purchase->calculateTotal();
        $this->em->flush();

        $items = $this->repo->findByPurchase($purchase);

        self::assertCount(1, $items);
        self::assertSame($purchase->getId(), $items[0]->getPurchase()->getId());
        self::assertSame($lesson->getId(), $items[0]->getLesson()->getId());
    }

    public function testFindByUserAndCursus(): void
    {
        $user = $this->getTestUser();
        $cursus = $this->getGuitarCursus();

        $purchase = (new Purchase())->setUser($user)->setStatus('paid')->setPaidAt(new \DateTimeImmutable());
        $purchase->generateOrderNumber();
        $this->em->persist($purchase);

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setCursus($cursus)
            ->setUnitPrice($cursus->getPrice())
            ->setQuantity(1);

        $this->em->persist($item);
        $purchase->addItem($item);
        $purchase->calculateTotal();
        $this->em->flush();

        $items = $this->repo->findByUserAndCursus($user, $cursus);

        self::assertCount(1, $items);
        self::assertSame($cursus->getId(), $items[0]->getCursus()->getId());
        self::assertSame($user->getId(), $items[0]->getPurchase()->getUser()->getId());
    }

    public function testFindByUserAndStatus(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $purchaseCart = (new Purchase())->setUser($user)->setStatus('cart');
        $purchaseCart->generateOrderNumber();
        $this->em->persist($purchaseCart);

        $itemCart = (new PurchaseItem())
            ->setPurchase($purchaseCart)
            ->setLesson($lesson)
            ->setUnitPrice($lesson->getPrice())
            ->setQuantity(1);

        $this->em->persist($itemCart);
        $purchaseCart->addItem($itemCart);

        $purchasePaid = (new Purchase())->setUser($user)->setStatus('paid')->setPaidAt(new \DateTimeImmutable());
        $purchasePaid->generateOrderNumber();
        $this->em->persist($purchasePaid);

        $itemPaid = (new PurchaseItem())
            ->setPurchase($purchasePaid)
            ->setLesson($lesson)
            ->setUnitPrice($lesson->getPrice())
            ->setQuantity(1);

        $this->em->persist($itemPaid);
        $purchasePaid->addItem($itemPaid);

        $this->em->flush();

        $cartItems = $this->repo->findByUserAndStatus($user, 'cart');
        self::assertCount(1, $cartItems);
        self::assertSame('cart', $cartItems[0]->getPurchase()->getStatus());

        $paidItems = $this->repo->findByUserAndStatus($user, 'paid');
        self::assertCount(1, $paidItems);
        self::assertSame('paid', $paidItems[0]->getPurchase()->getStatus());
    }

    public function testFindByUserAndPeriod(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $purchaseOld = (new Purchase())->setUser($user)->setStatus('paid')->setPaidAt(new \DateTimeImmutable());
        $purchaseOld->generateOrderNumber();
        $this->forceCreatedAt($purchaseOld, new \DateTimeImmutable('2024-01-01 10:00:00'));
        $this->em->persist($purchaseOld);

        $itemOld = (new PurchaseItem())
            ->setPurchase($purchaseOld)
            ->setLesson($lesson)
            ->setUnitPrice($lesson->getPrice())
            ->setQuantity(1);
        $this->em->persist($itemOld);

        $purchaseInRange = (new Purchase())->setUser($user)->setStatus('paid')->setPaidAt(new \DateTimeImmutable());
        $purchaseInRange->generateOrderNumber();
        $this->forceCreatedAt($purchaseInRange, new \DateTimeImmutable('2026-02-01 10:00:00'));
        $this->em->persist($purchaseInRange);

        $itemInRange = (new PurchaseItem())
            ->setPurchase($purchaseInRange)
            ->setLesson($lesson)
            ->setUnitPrice($lesson->getPrice())
            ->setQuantity(1);
        $this->em->persist($itemInRange);

        $this->em->flush();

        $from = new \DateTimeImmutable('2026-01-01 00:00:00');
        $to   = new \DateTimeImmutable('2026-03-01 00:00:00');

        $items = $this->repo->findByUserAndPeriod($user, $from, $to);

        self::assertCount(1, $items);
        self::assertSame($purchaseInRange->getId(), $items[0]->getPurchase()->getId());
    }
}