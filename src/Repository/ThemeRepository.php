<?php

namespace App\Repository;

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    /**
     * @return Theme[]
     */
    public function findThemesWithFilters(?string $name = null, ?float $minPrice = null, ?float $maxPrice = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->distinct()
            ->leftJoin('t.cursus', 'c', 'WITH', 'c.isActive = true')
            ->addSelect('c')
            ->leftJoin('c.lessons', 'l', 'WITH', 'l.isActive = true')
            ->addSelect('l')
            ->andWhere('t.isActive = true');

        if ($name) {
            $qb->andWhere('LOWER(t.name) LIKE :name')
            ->setParameter('name', '%'.mb_strtolower(trim($name)).'%');
        }

        if ($minPrice !== null && $maxPrice !== null) {
            $qb->andWhere('((c.price BETWEEN :minPrice AND :maxPrice) OR c.id IS NULL)')
               ->setParameter('minPrice', $minPrice)
               ->setParameter('maxPrice', $maxPrice);
        } else {
            if ($minPrice !== null) {
                $qb->andWhere('(c.price >= :minPrice OR c.id IS NULL)')
                   ->setParameter('minPrice', $minPrice);
            }
            if ($maxPrice !== null) {
                $qb->andWhere('(c.price <= :maxPrice OR c.id IS NULL)')
                   ->setParameter('maxPrice', $maxPrice);
            }
        }

        return $qb->orderBy('t.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    public function createActiveThemesQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = true')
            ->orderBy('t.name', 'ASC');
    }

    public function createAdminFilterQueryBuilder(
        ?string $q = null,
        string $status = 'all',
        string $sort = 'created_desc',
        bool $onlyActiveCursus = false,
        bool $requireCursus = false
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('t')->distinct();

        if ($onlyActiveCursus) {
            $qb->leftJoin('t.cursus', 'c', 'WITH', 'c.isActive = true');
        } else {
            $qb->leftJoin('t.cursus', 'c');
        }
        $qb->addSelect('c');

        // Si on veut exclure les thèmes sans cursus (dans le JOIN courant)
        if ($requireCursus) {
            $qb->andWhere('c.id IS NOT NULL');
        }

        if ($q) {
            $qb->andWhere('LOWER(t.name) LIKE :q')
            ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        if ($status === 'active') {
            $qb->andWhere('t.isActive = true');
        } elseif ($status === 'archived') {
            $qb->andWhere('t.isActive = false');
        }

        switch ($sort) {
            case 'name_asc':
                $qb->orderBy('t.name', 'ASC');
                break;
            case 'name_desc':
                $qb->orderBy('t.name', 'DESC');
                break;
            case 'created_asc':
                $qb->orderBy('t.createdAt', 'ASC');
                break;
            default:
                $qb->orderBy('t.createdAt', 'DESC');
        }

        $qb->addOrderBy('c.name', 'ASC');

        return $qb;
    }
}