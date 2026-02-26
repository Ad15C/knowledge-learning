<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return User[]
     */
    public function findForAdminList(
        ?string $search,
        string $sort = 'name',
        string $direction = 'ASC',
        bool $includeArchived = false
    ): array {
        $qb = $this->createQueryBuilder('u');

        if (!$includeArchived) {
            $qb->andWhere('u.archivedAt IS NULL');
        }
        // Recherche basique sur nom, prénom, email 
        if ($search) {
            $qb->andWhere('LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q OR LOWER(u.email) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($search).'%');
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        // Tri par date de création ou par nom
        if ($sort === 'recent') {
            // Si jamais un vieux user a createdAt NULL, on se rabat sur id
            $qb->addOrderBy('u.createdAt', $direction)
               ->addOrderBy('u.id', $direction);
        } else {
            $qb->orderBy('u.lastName', $direction)
               ->addOrderBy('u.firstName', $direction);
        }

        return $qb->getQuery()->getResult();
    }

    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.archivedAt IS NULL')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}