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
    public function __construct(private EntityManagerInterface $em) {}

    public function userCanAccessLesson(User $user, Lesson $lesson): bool
    {
        // Admin bypass
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
     * @return array<int,bool> map [lessonId => true]
     */
    public function getAccessibleLessonMapForCursus(User $user, Cursus $cursus): array
    {
        // Admin => accès à tout
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $all = [];
            foreach ($cursus->getLessons() as $lesson) {
                if ($lesson->getId()) {
                    $all[$lesson->getId()] = true;
                }
            }
            return $all;
        }

        // 1) Si achat du cursus payé => toutes les leçons
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
                if ($lesson->getId()) {
                    $all[$lesson->getId()] = true;
                }
            }
            return $all;
        }

        // 2) Sinon : achat leçon par leçon (paid) dans ce cursus
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
            if ($lesson && $lesson->getId()) {
                $map[$lesson->getId()] = true;
            }
        }

        return $map;
    }
}