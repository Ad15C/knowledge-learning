<?php

namespace App\DataFixtures;

use App\Entity\Purchase;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PurchaseFixtures extends Fixture implements DependentFixtureInterface
{
    public const PURCHASE_CART_REF = 'purchase_cart';
    public const PURCHASE_PENDING_REF = 'purchase_pending';
    public const PURCHASE_PAID_REF = 'purchase_paid';
    public const PURCHASE_CANCELED_REF = 'purchase_canceled';
    public const PURCHASE_ARCHIVED_USER_REF = 'purchase_archived_user';

    public function load(ObjectManager $manager): void
    {
        $user = $this->getReference(TestUserFixtures::USER_REF, User::class);
        $admin = $this->getReference(TestUserFixtures::ADMIN_REF, User::class);
        $archivedUser = $this->getReference(TestUserFixtures::ARCHIVED_USER_REF, User::class);

        // PURCHASE CART
        $purchaseCart = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_CART)
            ->setPaidAt(null);

        $this->setPrivateProperty($purchaseCart, 'createdAt', new \DateTimeImmutable('-12 days'));

        $manager->persist($purchaseCart);
        $this->addReference(self::PURCHASE_CART_REF, $purchaseCart);

        // PURCHASE PENDING
        $purchasePending = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_PENDING)
            ->setPaidAt(null);

        $this->setPrivateProperty($purchasePending, 'createdAt', new \DateTimeImmutable('-7 days'));

        $manager->persist($purchasePending);
        $this->addReference(self::PURCHASE_PENDING_REF, $purchasePending);

        // PURCHASE PAID
        $purchasePaid = (new Purchase())
            ->setUser($admin)
            ->setStatus(Purchase::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable('-4 days'));

        $this->setPrivateProperty($purchasePaid, 'createdAt', new \DateTimeImmutable('-5 days'));

        $manager->persist($purchasePaid);
        $this->addReference(self::PURCHASE_PAID_REF, $purchasePaid);

        // PURCHASE CANCELED
        $purchaseCanceled = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_CANCELED)
            ->setPaidAt(null);

        $this->setPrivateProperty($purchaseCanceled, 'createdAt', new \DateTimeImmutable('-3 days'));

        $manager->persist($purchaseCanceled);
        $this->addReference(self::PURCHASE_CANCELED_REF, $purchaseCanceled);

        // PURCHASE PAID liée à un user archivé
        $purchaseArchivedUser = (new Purchase())
            ->setUser($archivedUser)
            ->setStatus(Purchase::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable('-1 day'));

        $this->setPrivateProperty($purchaseArchivedUser, 'createdAt', new \DateTimeImmutable('-2 days'));

        $manager->persist($purchaseArchivedUser);
        $this->addReference(self::PURCHASE_ARCHIVED_USER_REF, $purchaseArchivedUser);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            TestUserFixtures::class,
        ];
    }

    private function setPrivateProperty(object $entity, string $property, mixed $value): void
    {
        $reflection = new \ReflectionObject($entity);

        while (!$reflection->hasProperty($property) && $reflection = $reflection->getParentClass()) {
        }

        if (!$reflection || !$reflection->hasProperty($property)) {
            throw new \RuntimeException(sprintf(
                'La propriété "%s" est introuvable sur %s.',
                $property,
                $entity::class
            ));
        }

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($entity, $value);
    }
}