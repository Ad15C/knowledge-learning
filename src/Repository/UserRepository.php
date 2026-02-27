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

    public function countActiveAdmins(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.archivedAt IS NULL')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_ADMIN"%')
            ->getQuery()
            ->getSingleScalarResult();
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

        if ($search) {
            $qb->andWhere('LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q OR LOWER(u.email) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($search).'%');
        }

        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        if ($sort === 'recent') {
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