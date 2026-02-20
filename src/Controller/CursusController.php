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
        $user = $this->getUser(); // Récupère l'utilisateur connecté

        // Requête pour récupérer tous les items payés de cet utilisateur
        $paidItems = $em->getRepository(PurchaseItem::class)
            ->createQueryBuilder('pi')
            ->join('pi.purchase', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getResult();

        // Récupérztion des cursus associés par exemple
        $cursusPaid = [];
        foreach ($paidItems as $item) {
            if ($item->getCursus()) {
                $cursusPaid[] = $item->getCursus()->getId();
            }
        }

        // Récupération du cursus affiché
        $cursus = $em->getRepository('App\Entity\Cursus')->find($id);

        return $this->render('cursus/show.html.twig', [
            'cursus' => $cursus,
            'userHasAccess' => array_flip($cursusPaid), // pour le twig, presence = accès
        ]);
    }
}