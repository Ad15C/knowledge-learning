<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    private function applySearch(QueryBuilder $qb, string $q): void
    {
        $expr = $qb->expr();

        $qb->andWhere(
            $expr->orX(
                $expr->like('LOWER(u.firstName)', ':q'),
                $expr->like('LOWER(u.lastName)', ':q'),
                $expr->like('LOWER(u.email)', ':q')
            )
        )->setParameter('q', '%'.mb_strtolower($q).'%');
    }

    /**
     * Désactive temporairement tous les filtres Doctrine activés,
     * exécute $fn, puis réactive les filtres.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    private function withoutEnabledDoctrineFilters(callable $fn)
    {
        $filters = $this->getEntityManager()->getFilters();
        $enabled = array_keys($filters->getEnabledFilters());

        foreach ($enabled as $name) {
            $filters->disable($name);
        }

        try {
            return $fn();
        } finally {
            foreach ($enabled as $name) {
                $filters->enable($name);
            }
        }
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
        $runner = function () use ($search, $sort, $direction, $includeArchived): array {
            $qb = $this->createQueryBuilder('u');

            if (!$includeArchived) {
                $qb->andWhere('u.archivedAt IS NULL');
            }

            if ($search) {
                $this->applySearch($qb, $search);
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
        };

        // si on veut inclure les archivés, on bypass tous les filtres Doctrine actifs
        return $includeArchived
            ? $this->withoutEnabledDoctrineFilters($runner)
            : $runner();
    }

    public function findForAdminListPaginated(
        string $q,
        string $status, // active|archived|all
        string $sort,
        string $dir,
        int $page,
        int $perPage
    ): array {
        $runner = function () use ($q, $status, $sort, $dir, $page, $perPage): array {
            $qb = $this->createQueryBuilder('u');

            if ($q !== '') {
                $this->applySearch($qb, $q);
            }

            if ($status === 'active') {
                $qb->andWhere('u.archivedAt IS NULL');
            } elseif ($status === 'archived') {
                $qb->andWhere('u.archivedAt IS NOT NULL');
            } // all => pas de filtre

            $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

            if ($sort === 'recent') {
                $qb->orderBy('u.createdAt', $dir)
                   ->addOrderBy('u.id', $dir);
            } else {
                $qb->orderBy('u.lastName', $dir)
                   ->addOrderBy('u.firstName', $dir);
            }

            $qb->setFirstResult(($page - 1) * $perPage)
               ->setMaxResults($perPage);

            $paginator = new Paginator($qb->getQuery(), true);

            return [
                'items' => iterator_to_array($paginator->getIterator(), false),
                'total' => count($paginator),
            ];
        };

        // Si on veut voir archived/all, on bypass les filtres globaux
        $needsArchived = ($status === 'archived' || $status === 'all');

        return $needsArchived
            ? $this->withoutEnabledDoctrineFilters($runner)
            : $runner();
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