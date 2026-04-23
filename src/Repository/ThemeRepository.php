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
     * FRONT : Thèmes visibles
     * - Theme actif
     * - au moins 1 cursus actif
     * - au moins 1 leçon active (dans un cursus actif)
     *
     * @return Theme[]
     */
    public function findVisibleThemesWithFilters(?string $name = null, ?float $minPrice = null, ?float $maxPrice = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->distinct()
            ->andWhere('t.isActive = true')
            ->innerJoin('t.cursus', 'c', 'WITH', 'c.isActive = true')
            ->innerJoin('c.lessons', 'l', 'WITH', 'l.isActive = true')
            ->addSelect('c', 'l');

        if ($name) {
            $qb->andWhere('LOWER(t.name) LIKE :name')
               ->setParameter('name', '%'.mb_strtolower(trim($name)).'%');
        }

        if ($minPrice !== null) {
            $qb->andWhere('c.price >= :minPrice')
               ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('c.price <= :maxPrice')
               ->setParameter('maxPrice', $maxPrice);
        }

        return $qb->orderBy('t.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * FRONT : un thème visible + charge cursus/lessons visibles (utile page show)
     */
     /* Méthode pour URL avec slug */
    public function findVisibleThemeBySlug(string $slug): ?Theme
    {
        return $this->createQueryBuilder('t')
            ->distinct()
            ->andWhere('t.slug = :slug')
            ->andWhere('t.isActive = true')
            ->setParameter('slug', $slug)
            ->innerJoin('t.cursus', 'c', 'WITH', 'c.isActive = true')
            ->innerJoin('c.lessons', 'l', 'WITH', 'l.isActive = true')
            ->addSelect('c', 'l')
            ->getQuery()
            ->getOneOrNullResult();
    }

    // -------------------- ADMIN --------------------

    public function createActiveThemesQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.isActive = true')
            ->orderBy('t.name', 'ASC');
    }

    /**
     * ADMIN : filtre classique (ne pas confondre "actif" et "visible")
     */
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

    /**
     * ADMIN : liste des thèmes + flag "visible sur le site" en 1 requête (sans N+1)
     *
     * @return array<int, array{theme: Theme, is_visible: bool}>
     */
    public function findAdminThemesWithVisibility(
        ?string $q = null,
        string $status = 'all',
        string $sort = 'created_desc',
        bool $onlyActiveCursus = true,
        bool $requireCursus = false
    ): array {
        $qb = $this->createQueryBuilder('t')->distinct();

        if ($onlyActiveCursus) {
            $qb->leftJoin('t.cursus', 'c', 'WITH', 'c.isActive = true');
        } else {
            $qb->leftJoin('t.cursus', 'c');
        }
        $qb->addSelect('c');

        if ($requireCursus) {
            $qb->andWhere('c.id IS NOT NULL');
        }

        $qb->addSelect(
            "CASE WHEN (t.isActive = true AND EXISTS (
                SELECT 1
                FROM App\Entity\Cursus c2
                JOIN c2.lessons l2 WITH l2.isActive = true
                WHERE c2.theme = t AND c2.isActive = true
            )) THEN 1 ELSE 0 END AS is_visible"
        );

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

        $rows = $qb->getQuery()->getResult();

        $out = [];
        foreach ($rows as $row) {
            /** @var Theme $theme */
            $theme = $row[0];
            $isVisible = (bool) $row['is_visible'];

            $out[] = [
                'theme' => $theme,
                'is_visible' => $isVisible,
            ];
        }

        return $out;
    }
}