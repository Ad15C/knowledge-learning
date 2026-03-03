<?php

namespace App\Tests\Repository;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\User;
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
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        // Reload fixtures fresh
        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);

        $repo = $this->em->getRepository(PurchaseItem::class);
        self::assertInstanceOf(PurchaseItemRepository::class, $repo);
        $this->repo = $repo;

        $this->em->clear();
    }

    private function getTestUser(): User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user);
        return $user;
    }

    private function getGuitarCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)
            ->findOneBy(['name' => 'Cursus d’initiation à la guitare']);

        self::assertNotNull($cursus);
        return $cursus;
    }

    private function getOneLesson(): Lesson
    {
        $lesson = $this->em->getRepository(Lesson::class)
            ->findOneBy(['title' => 'Découverte de l’instrument']);

        self::assertNotNull($lesson);
        return $lesson;
    }

    private function forceCreatedAt(Purchase $purchase, \DateTimeImmutable $dt): void
    {
        $ref = new \ReflectionClass($purchase);
        $prop = $ref->getProperty('createdAt');
        $prop->setAccessible(true);
        $prop->setValue($purchase, $dt);
    }

    private function createPurchase(User $user, string $status): Purchase
    {
        $purchase = new Purchase();
        $purchase->setUser($user)->setStatus($status);

        // paidAt uniquement si paid
        if ($status === Purchase::STATUS_PAID) {
            $purchase->setPaidAt(new \DateTimeImmutable('2026-02-01 10:00:00'));
        }

        // En vrai c'est Doctrine PrePersist qui le fait, mais pour test on appelle explicitement
        $purchase->generateOrderNumber();

        $this->em->persist($purchase);
        return $purchase;
    }

    public function testFindByPurchase(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $purchase = $this->createPurchase($user, Purchase::STATUS_CART);

        $item = new PurchaseItem();
        $item->setLesson($lesson)
            ->setUnitPrice((float) $lesson->getPrice())
            ->setQuantity(1);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->flush();
        $this->em->clear();

        $purchaseReloaded = $this->em->getRepository(Purchase::class)->find($purchase->getId());
        self::assertNotNull($purchaseReloaded);

        $items = $this->repo->findByPurchase($purchaseReloaded);

        self::assertCount(1, $items);
        self::assertSame($purchaseReloaded->getId(), $items[0]->getPurchase()->getId());
        self::assertSame($lesson->getId(), $items[0]->getLesson()->getId());
    }

    public function testFindByUserAndCursus(): void
    {
        $user = $this->getTestUser();
        $cursus = $this->getGuitarCursus();

        $purchase = $this->createPurchase($user, Purchase::STATUS_PAID);

        $item = new PurchaseItem();
        $item->setCursus($cursus)
            ->setUnitPrice((float) $cursus->getPrice())
            ->setQuantity(1);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->flush();
        $this->em->clear();

        $userReloaded = $this->em->getRepository(User::class)->find($user->getId());
        $cursusReloaded = $this->em->getRepository(Cursus::class)->find($cursus->getId());
        self::assertNotNull($userReloaded);
        self::assertNotNull($cursusReloaded);

        $items = $this->repo->findByUserAndCursus($userReloaded, $cursusReloaded);

        self::assertCount(1, $items);
        self::assertNotNull($items[0]->getCursus());
        self::assertSame($cursusReloaded->getId(), $items[0]->getCursus()->getId());
        self::assertSame($userReloaded->getId(), $items[0]->getPurchase()->getUser()->getId());
    }

    public function testFindByUserAndStatus(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $purchaseCart = $this->createPurchase($user, Purchase::STATUS_CART);
        $itemCart = (new PurchaseItem())
            ->setLesson($lesson)
            ->setUnitPrice((float) $lesson->getPrice())
            ->setQuantity(1);
        $purchaseCart->addItem($itemCart);

        $purchasePaid = $this->createPurchase($user, Purchase::STATUS_PAID);
        $itemPaid = (new PurchaseItem())
            ->setLesson($lesson)
            ->setUnitPrice((float) $lesson->getPrice())
            ->setQuantity(1);
        $purchasePaid->addItem($itemPaid);

        $this->em->flush();
        $this->em->clear();

        $userReloaded = $this->em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($userReloaded);

        $cartItems = $this->repo->findByUserAndStatus($userReloaded, Purchase::STATUS_CART);
        self::assertCount(1, $cartItems);
        self::assertSame(Purchase::STATUS_CART, $cartItems[0]->getPurchase()->getStatus());

        $paidItems = $this->repo->findByUserAndStatus($userReloaded, Purchase::STATUS_PAID);
        self::assertCount(1, $paidItems);
        self::assertSame(Purchase::STATUS_PAID, $paidItems[0]->getPurchase()->getStatus());
    }

    public function testFindByUserAndPeriodFiltersOnPurchaseCreatedAt(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $purchaseOld = $this->createPurchase($user, Purchase::STATUS_PAID);
        $this->forceCreatedAt($purchaseOld, new \DateTimeImmutable('2024-01-01 10:00:00'));
        $purchaseOld->addItem(
            (new PurchaseItem())
                ->setLesson($lesson)
                ->setUnitPrice((float) $lesson->getPrice())
                ->setQuantity(1)
        );

        $purchaseInRange = $this->createPurchase($user, Purchase::STATUS_PAID);
        $this->forceCreatedAt($purchaseInRange, new \DateTimeImmutable('2026-02-01 10:00:00'));
        $purchaseInRange->addItem(
            (new PurchaseItem())
                ->setLesson($lesson)
                ->setUnitPrice((float) $lesson->getPrice())
                ->setQuantity(1)
        );

        $this->em->flush();
        $this->em->clear();

        $userReloaded = $this->em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($userReloaded);

        $from = new \DateTimeImmutable('2026-01-01 00:00:00');
        $to   = new \DateTimeImmutable('2026-03-01 00:00:00');

        $items = $this->repo->findByUserAndPeriod($userReloaded, $from, $to);

        self::assertCount(1, $items);
        self::assertSame($purchaseInRange->getId(), $items[0]->getPurchase()->getId());
    }

    public function testFindLessonsPurchasedByUserExcludesCartAndReturnsLessons(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        // Achat en panier -> doit être exclu
        $cart = $this->createPurchase($user, Purchase::STATUS_CART);
        $cart->addItem(
            (new PurchaseItem())
                ->setLesson($lesson)
                ->setUnitPrice((float) $lesson->getPrice())
                ->setQuantity(1)
        );

        // Achat payé -> doit être inclus
        $paid = $this->createPurchase($user, Purchase::STATUS_PAID);
        $paid->addItem(
            (new PurchaseItem())
                ->setLesson($lesson)
                ->setUnitPrice((float) $lesson->getPrice())
                ->setQuantity(1)
        );

        $this->em->flush();
        $this->em->clear();

        $userReloaded = $this->em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($userReloaded);

        $lessons = $this->repo->findLessonsPurchasedByUser($userReloaded);

        // DQL retourne des Lesson (avec joins addSelect), donc on vérifie la présence de notre lesson
        self::assertNotEmpty($lessons);

        $ids = array_map(
            fn($l) => $l instanceof Lesson ? $l->getId() : null,
            $lessons
        );

        self::assertContains($lesson->getId(), $ids);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em, $this->repo);
        self::ensureKernelShutdown();
    }
}