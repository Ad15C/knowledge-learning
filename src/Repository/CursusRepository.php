<?php

namespace App\Repository;

use App\Entity\Cursus;
use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class CursusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cursus::class);
    }

    /**
     * ADMIN
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

   public function findVisibleWithVisibleLessonsBySlug(string $slug): ?Cursus
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.theme', 't')
            ->addSelect('t')
            ->leftJoin('c.lessons', 'l', 'WITH', 'l.isActive = true')
            ->addSelect('l')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.isActive = true')
            ->andWhere('t.isActive = true')
            ->setParameter('slug', $slug)
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
                ->setParameter('q', '%' . mb_strtolower(trim($q)) . '%');
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
                break;
        }

        return $qb;
    }

    public function findVisibleCursusBySlug(string $slug): ?Cursus
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.theme', 't')
            ->addSelect('t')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.isActive = true')
            ->andWhere('t.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}