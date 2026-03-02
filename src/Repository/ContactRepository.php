<?php

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * Retourne les messages non lus et non traités
     */
    public function findUnread(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.readAt IS NULL')
            ->andWhere('c.handled = false')
            ->orderBy('c.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avec filtres
     *
     * $filters = [
     *   'subject' => 'payment',
     *   'status'  => 'unread'|'read'|'handled',
     *   'q'       => 'texte'
     * ]
     */
    public function findByFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('c');

        if (!empty($filters['subject'])) {
            $qb->andWhere('c.subject = :subject')
            ->setParameter('subject', $filters['subject']);
        }

        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'unread':
                    $qb->andWhere('c.readAt IS NULL')
                    ->andWhere('c.handled = false');
                    break;

                case 'read':
                    $qb->andWhere('c.readAt IS NOT NULL')
                    ->andWhere('c.handled = false');
                    break;

                case 'handled':
                    $qb->andWhere('c.handled = true');
                    break;
            }
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $qb->andWhere('c.fullname LIKE :q OR c.email LIKE :q OR c.message LIKE :q')
            ->setParameter('q', '%' . $q . '%');
        }

        return $qb->orderBy('c.sentAt', 'DESC')->getQuery()->getResult();
    }
}