<?php

namespace App\Controller;

use App\Entity\Cursus;
use App\Entity\PurchaseItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CursusController extends AbstractController
{
    #[Route('/cursus/{id}', name: 'cursus_show')]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        /** @var Cursus|null $cursus */
        $cursus = $em->getRepository(Cursus::class)->find($id);

        if (!$cursus || !$cursus->isPubliclyAccessible()) {
            throw $this->createNotFoundException('Cursus introuvable.');
        }

        $userHasAccess = [];

        if ($user !== null) {
            $paidItems = $em->getRepository(PurchaseItem::class)
                ->createQueryBuilder('pi')
                ->join('pi.purchase', 'p')
                ->leftJoin('pi.lesson', 'l')
                ->leftJoin('l.cursus', 'lc')      // cursus de la leçon achetée
                ->leftJoin('pi.cursus', 'c')      // cursus acheté
                ->addSelect('l', 'lc', 'c')
                ->andWhere('p.user = :user')
                ->andWhere('p.status = :status')
                ->andWhere('(c = :cursus OR lc = :cursus)')
                ->setParameter('user', $user)
                ->setParameter('status', 'paid')
                ->setParameter('cursus', $cursus)
                ->getQuery()
                ->getResult();

            foreach ($paidItems as $item) {
                if ($item->getLesson() && $item->getLesson()->getCursus()?->getId() === $cursus->getId()) {
                    $userHasAccess[$item->getLesson()->getId()] = true;
                }

                if ($item->getCursus() && $item->getCursus()->getId() === $cursus->getId()) {
                    foreach ($cursus->getLessons() as $lesson) {
                        $userHasAccess[$lesson->getId()] = true;
                    }
                }
            }
        }

        return $this->render('cursus/show.html.twig', [
            'cursus' => $cursus,
            'userHasAccess' => $userHasAccess,
            'userHasCompleted' => [],
        ]);
    }
}