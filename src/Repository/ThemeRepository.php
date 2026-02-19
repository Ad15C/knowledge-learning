<?php

namespace App\Repository;

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    /**
     * Récupère tous les thèmes avec leurs cursus et leçons.
     *
     * @return Theme[]
     */
    public function findThemesWithFilters(?string $name = null, ?float $minPrice = null, ?float $maxPrice = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.cursus', 'c')
            ->addSelect('c')
            ->leftJoin('c.lessons', 'l')
            ->addSelect('l');

        if ($name) {
            $qb->andWhere('t.name LIKE :name')
               ->setParameter('name', '%'.$name.'%');
        }

        if ($minPrice !== null) {
            $qb->andWhere('c.price >= :minPrice')
               ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('c.price <= :maxPrice')
               ->setParameter('maxPrice', $maxPrice);
        }

        return $qb->orderBy('t.name', 'ASC')->getQuery()->getResult();
    }
}
