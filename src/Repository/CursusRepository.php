<?php

namespace App\Repository;

use App\Entity\Cursus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

class CursusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cursus::class);
    }

    public function findWithLessons(int $id): ?Cursus
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.lessons', 'l')
            ->addSelect('l')
            ->andWhere('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function createAdminFilterQueryBuilder(
        ?string $q = null,
        ?string $status = 'all',
        ?int $themeId = null,
        ?string $sort = 'id_desc'
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.theme', 't')
            ->addSelect('t');

        if ($q) {
            $qb->andWhere('LOWER(c.name) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        if ($status === 'active') {
            $qb->andWhere('c.isActive = true');
        } elseif ($status === 'archived') {
            $qb->andWhere('c.isActive = false');
        }

        if ($themeId) {
            $qb->andWhere('t.id = :themeId')
               ->setParameter('themeId', $themeId);
        }

        switch ($sort) {
            case 'name_asc':
                $qb->orderBy('c.name', 'ASC');
                break;

            case 'name_desc':
                $qb->orderBy('c.name', 'DESC');
                break;

            case 'price_asc':
                // NULL en dernier, puis prix croissant
                $qb->orderBy('CASE WHEN c.price IS NULL THEN 1 ELSE 0 END', 'ASC')
                   ->addOrderBy('c.price', 'ASC');
                break;

            case 'price_desc':
                // NULL en dernier, puis prix décroissant
                $qb->orderBy('CASE WHEN c.price IS NULL THEN 1 ELSE 0 END', 'ASC')
                   ->addOrderBy('c.price', 'DESC');
                break;

            default:
                $qb->orderBy('c.id', 'DESC');
        }

        return $qb;
    }
}