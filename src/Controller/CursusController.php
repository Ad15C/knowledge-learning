<?php

namespace App\Controller;

use App\Entity\PurchaseItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CursusController extends AbstractController
{
    #[Route('/cursus/{id}', name: 'cursus_show')]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $cursus = $em->getRepository(\App\Entity\Cursus::class)->find($id);
        if (!$cursus) {
            throw $this->createNotFoundException();
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
                ->andWhere('(c = :cursus OR lc = :cursus)') // uniquement ce cursus
                ->setParameter('user', $user)
                ->setParameter('status', 'paid')
                ->setParameter('cursus', $cursus)
                ->getQuery()
                ->getResult();

            foreach ($paidItems as $item) {
                // Achat d'une leçon => accès à cette leçon (du cursus courant)
                if ($item->getLesson() && $item->getLesson()->getCursus()?->getId() === $cursus->getId()) {
                    $userHasAccess[$item->getLesson()->getId()] = true;
                }

                // Achat du cursus courant => accès à toutes ses leçons
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