<?php

namespace App\Repository;

use App\Entity\LessonValidated;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LessonValidatedRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonValidated::class);
    }

    /**
     * Vérifie si l'utilisateur a validé toutes les leçons d'un thème
     */
    public function hasCompletedTheme(User $user, Theme $theme): bool
    {
        $validatedLessons = $this->createQueryBuilder('lv')
            ->select('COUNT(lv.id)')
            ->join('lv.lesson', 'l')
            ->join('l.cursus', 'c')
            ->andWhere('lv.user = :user')
            ->andWhere('c.theme = :theme')
            ->setParameter('user', $user)
            ->setParameter('theme', $theme)
            ->getQuery()
            ->getSingleScalarResult();

        $totalLessons = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(Lesson::class, 'l')
            ->join('l.cursus', 'c')
            ->andWhere('c.theme = :theme')
            ->setParameter('theme', $theme)
            ->getQuery()
            ->getSingleScalarResult();

        return $validatedLessons >= $totalLessons;
    }

}
