<?php

namespace App\Service;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class LessonAccessService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function userCanAccessLesson(User $user, Lesson $lesson): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $qb = $this->em->getRepository(PurchaseItem::class)->createQueryBuilder('pi');

        $qb->join('pi.purchase', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('p.status = :paid')
            ->andWhere(
                $qb->expr()->orX(
                    'pi.lesson = :lesson',
                    'pi.cursus = :cursus'
                )
            )
            ->setParameter('user', $user)
            ->setParameter('paid', Purchase::STATUS_PAID)
            ->setParameter('lesson', $lesson)
            ->setParameter('cursus', $lesson->getCursus())
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * @return array<int,bool>
     */
    public function getAccessibleLessonMapForCursus(User $user, Cursus $cursus): array
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $all = [];
            foreach ($cursus->getLessons() as $lesson) {
                if ($lesson->getId() !== null) {
                    $all[$lesson->getId()] = true;
                }
            }
            return $all;
        }

        $qbCursus = $this->em->getRepository(PurchaseItem::class)->createQueryBuilder('pi');
        $qbCursus->join('pi.purchase', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('p.status = :paid')
            ->andWhere('pi.cursus = :cursus')
            ->setParameter('user', $user)
            ->setParameter('paid', Purchase::STATUS_PAID)
            ->setParameter('cursus', $cursus)
            ->setMaxResults(1);

        if ($qbCursus->getQuery()->getOneOrNullResult() !== null) {
            $all = [];
            foreach ($cursus->getLessons() as $lesson) {
                if ($lesson->getId() !== null) {
                    $all[$lesson->getId()] = true;
                }
            }
            return $all;
        }

        $qb = $this->em->getRepository(PurchaseItem::class)->createQueryBuilder('pi');
        $qb->join('pi.purchase', 'p')
            ->join('pi.lesson', 'l')
            ->andWhere('p.user = :user')
            ->andWhere('p.status = :paid')
            ->andWhere('l.cursus = :cursus')
            ->setParameter('user', $user)
            ->setParameter('paid', Purchase::STATUS_PAID)
            ->setParameter('cursus', $cursus);

        $items = $qb->getQuery()->getResult();

        $map = [];
        foreach ($items as $item) {
            $lesson = $item->getLesson();
            if ($lesson !== null && $lesson->getId() !== null) {
                $map[$lesson->getId()] = true;
            }
        }

        return $map;
    }
}