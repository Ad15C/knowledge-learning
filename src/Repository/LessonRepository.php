<?php

namespace App\Repository;

use App\Entity\Lesson;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Lesson>
 */
class LessonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lesson::class);
    }

    public function createAdminFilterQueryBuilder(
        ?string $q = null,
        string $status = 'all',
        ?int $cursusId = null,
        ?int $themeId = null,
        string $sort = 'id_desc'
    ): QueryBuilder {
        $qb = $this->createQueryBuilder('l')
            ->distinct()
            ->leftJoin('l.cursus', 'c')->addSelect('c')
            ->leftJoin('c.theme', 't')->addSelect('t');

        if ($q) {
            $qb->andWhere('LOWER(l.title) LIKE :q')
            ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        if ($status === 'active') {
            $qb->andWhere('l.isActive = true');
        } elseif ($status === 'archived') {
            $qb->andWhere('l.isActive = false');
        }

        if ($cursusId) {
            $qb->andWhere('c.id = :cursusId')->setParameter('cursusId', $cursusId);
        }

        if ($themeId) {
            $qb->andWhere('t.id = :themeId')->setParameter('themeId', $themeId);
        }

        switch ($sort) {
            case 'title_asc':  $qb->orderBy('l.title', 'ASC'); break;
            case 'title_desc': $qb->orderBy('l.title', 'DESC'); break;
            case 'price_asc':
                $qb->addOrderBy('CASE WHEN l.price IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('l.price', 'ASC');
                break;
            case 'price_desc':
                $qb->addOrderBy('CASE WHEN l.price IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('l.price', 'DESC');
                break;
            default:           $qb->orderBy('l.id', 'DESC');
        }

        return $qb;
    }
}
