<?php

namespace App\Repository;

use App\Entity\Certification;
use App\Entity\User;
use App\Entity\Cursus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Certification>
 */
class CertificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certification::class);
    }

    // Toutes les certifications d'un utilisateur
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.issuedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Toutes les certifications d'un utilisateur pour un cursus spécifique
    public function findByUserAndCursus(User $user, Cursus $cursus): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.cursus = :cursus')
            ->setParameters([
                'user' => $user,
                'cursus' => $cursus,
            ])
            ->orderBy('c.issuedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Recherche par période pour un utilisateur
    public function findByUserAndPeriod(User $user, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.issuedAt BETWEEN :from AND :to')
            ->setParameters([
                'user' => $user,
                'from' => $from,
                'to' => $to,
            ])
            ->orderBy('c.issuedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // Compter le nombre de certifications pour un utilisateur (optionnel : par cursus)
    public function countByUser(User $user, ?Cursus $cursus = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user);

        if ($cursus) {
            $qb->andWhere('c.cursus = :cursus')
               ->setParameter('cursus', $cursus);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
