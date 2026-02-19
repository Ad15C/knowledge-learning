<?php

namespace App\Repository;

use App\Entity\PurchaseItem;
use App\Entity\User;
use App\Entity\Cursus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PurchaseItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseItem::class);
    }

    // Tous les items d'une commande
    public function findByPurchase($purchase): array
    {
        return $this->createQueryBuilder('pi')
            ->andWhere('pi.purchase = :purchase')
            ->setParameter('purchase', $purchase)
            ->getQuery()
            ->getResult();
    }

    // Recherche par utilisateur et cursus/cours
    public function findByUserAndCursus(User $user, Cursus $cursus): array
    {
        return $this->createQueryBuilder('pi')
            ->join('pi.purchase', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('pi.cursus = :cursus')
            ->setParameters([
                'user' => $user,
                'cursus' => $cursus,
            ])
            ->getQuery()
            ->getResult();
    }

    // Recherche par utilisateur et statut de la commande
    public function findByUserAndStatus(User $user, string $status): array
    {
        return $this->createQueryBuilder('pi')
            ->join('pi.purchase', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('p.status = :status')
            ->setParameters([
                'user' => $user,
                'status' => $status,
            ])
            ->getQuery()
            ->getResult();
    }

    // Recherche par utilisateur et période
    public function findByUserAndPeriod(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('pi')
            ->join('pi.purchase', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('p.createdAt BETWEEN :from AND :to')
            ->setParameters([
                'user' => $user,
                'from' => $from,
                'to' => $to,
            ])
            ->getQuery()
            ->getResult();
    }
}
