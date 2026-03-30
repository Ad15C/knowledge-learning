<?php

namespace App\Service;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
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

        // 1) Si la leçon a déjà été validée, on autorise l'accès
        $validated = $this->em->getRepository(LessonValidated::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
            'completed' => true,
        ]);

        if ($validated !== null) {
            return true;
        }

        // 2) Sinon, on vérifie l'achat de la leçon ou du cursus
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

        $map = [];

        // 1) Leçons validées = accessibles
        $validatedLessons = $this->em->getRepository(LessonValidated::class)->createQueryBuilder('lv')
            ->join('lv.lesson', 'l')
            ->andWhere('lv.user = :user')
            ->andWhere('lv.completed = :completed')
            ->andWhere('l.cursus = :cursus')
            ->setParameter('user', $user)
            ->setParameter('completed', true)
            ->setParameter('cursus', $cursus)
            ->getQuery()
            ->getResult();

        foreach ($validatedLessons as $validated) {
            $lesson = $validated->getLesson();
            if ($lesson !== null && $lesson->getId() !== null) {
                $map[$lesson->getId()] = true;
            }
        }

        // 2) Si le cursus entier a été acheté, toutes les leçons sont accessibles
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
            foreach ($cursus->getLessons() as $lesson) {
                if ($lesson->getId() !== null) {
                    $map[$lesson->getId()] = true;
                }
            }

            return $map;
        }

        // 3) Leçons achetées individuellement
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

        foreach ($items as $item) {
            $lesson = $item->getLesson();
            if ($lesson !== null && $lesson->getId() !== null) {
                $map[$lesson->getId()] = true;
            }
        }

        return $map;
    }
}