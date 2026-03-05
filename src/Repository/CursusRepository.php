<?php

namespace App\Repository;

use App\Entity\Cursus;
use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

class CursusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cursus::class);
    }

    /**
     * ADMIN (inchangé)
     */
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

    /**
     * FRONT : cursus visibles d'un thème
     * - cursus actif
     * - thème actif
     * - au moins 1 leçon active
     *
     * @return Cursus[]
     */
    public function findVisibleByTheme(Theme $theme): array
    {
        return $this->createQueryBuilder('c')
            ->distinct()
            ->innerJoin('c.theme', 't')
            ->innerJoin('c.lessons', 'l', 'WITH', 'l.isActive = true')
            ->addSelect('t', 'l')
            ->andWhere('c.isActive = true')
            ->andWhere('t.isActive = true')
            ->andWhere('t = :theme')
            ->setParameter('theme', $theme)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * FRONT : un cursus visible + charge ses leçons visibles (page show)
     */
    public function findVisibleWithVisibleLessons(int $id): ?Cursus
    {
        return $this->createQueryBuilder('c')
            ->distinct()
            ->innerJoin('c.theme', 't')
            ->innerJoin('c.lessons', 'l', 'WITH', 'l.isActive = true')
            ->addSelect('t', 'l')
            ->andWhere('c.id = :id')
            ->andWhere('c.isActive = true')
            ->andWhere('t.isActive = true')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // -------------------- ADMIN FILTER (inchangé chez toi) --------------------

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
                $qb->orderBy('CASE WHEN c.price IS NULL THEN 1 ELSE 0 END', 'ASC')
                   ->addOrderBy('c.price', 'ASC');
                break;
            case 'price_desc':
                $qb->orderBy('CASE WHEN c.price IS NULL THEN 1 ELSE 0 END', 'ASC')
                   ->addOrderBy('c.price', 'DESC');
                break;
            default:
                $qb->orderBy('c.id', 'DESC');
        }

        return $qb;
    }
}