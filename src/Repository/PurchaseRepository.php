<?php

namespace App\Repository;

use App\Entity\Purchase;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class PurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Purchase::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

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

    public function getTotalSpent(User $user, ?string $status = null): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.total)')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        return (float) $qb->getQuery()->getSingleScalarResult();
    }

    public function getTotalOrders(User $user, ?string $status = null): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array{items: Purchase[], total: int}
     */
    public function findForAdminListPaginated(
        string $q,
        ?string $status,
        ?int $userId,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateTo,
        string $sort,
        string $dir,
        int $page,
        int $perPage
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u');

        if ($q !== '') {
            $qb->andWhere('LOWER(p.orderNumber) LIKE :q OR LOWER(u.email) LIKE :q OR LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        if ($userId) {
            $qb->andWhere('u.id = :uid')->setParameter('uid', $userId);
        }

        if ($dateFrom) {
            $qb->andWhere('p.createdAt >= :from')->setParameter('from', $dateFrom);
        }
        if ($dateTo) {
            $qb->andWhere('p.createdAt <= :to')->setParameter('to', $dateTo->setTime(23, 59, 59));
        }

        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $allowedSort = [
            'createdAt' => 'p.createdAt',
            'status'    => 'p.status',
            'total'     => 'p.total',
            'paidAt'    => 'p.paidAt',
            'user'      => 'u.lastName',
        ];
        $sortExpr = $allowedSort[$sort] ?? 'p.createdAt';

        $qb->orderBy($sortExpr, $dir)
           ->addOrderBy('p.id', $dir)
           ->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        $paginator = new Paginator($qb->getQuery(), true);

        return [
            'items' => iterator_to_array($paginator->getIterator(), false),
            'total' => count($paginator),
        ];
    }

    public function findOneForAdminShow(int $id): ?Purchase
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.items', 'pi')->addSelect('pi')
            ->leftJoin('pi.lesson', 'l')->addSelect('l')
            ->leftJoin('pi.cursus', 'c')->addSelect('c')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}