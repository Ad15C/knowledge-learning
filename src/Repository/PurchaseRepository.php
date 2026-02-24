<?php

namespace App\Repository;

use App\Entity\Purchase;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Purchase::class);
    }

    // Toutes les commandes d'un utilisateur
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Filtrer par utilisateur et statut
    public function findByUserAndStatus(User $user, string $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Filtrer par utilisateur et période
    public function findByUserAndPeriod(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.createdAt BETWEEN :from AND :to')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Total dépensé par utilisateur (optionnel : filtrer par statut)
    public function getTotalSpent(User $user, ?string $status = null): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.total)');

        $qb->andWhere('p.user = :user')
           ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    // Nombre de commandes pour un utilisateur (optionnel : filtrer par statut)
    public function getTotalOrders(User $user, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
