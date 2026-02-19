<?php

namespace App\Controller;

use App\Entity\Purchase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/', name: 'homepage')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'cartItemCount' => $this->getCartItemCount(), // passer au template
        ]);
    }

    public function getCartItemCount(): int
    {
        $user = $this->getUser();
        if (!$user) return 0;

        $purchase = $this->em->getRepository(Purchase::class)->findOneBy([
            'user' => $user,
            'status' => 'cart'
        ]);

        return $purchase ? count($purchase->getItems()) : 0;
    }
}
