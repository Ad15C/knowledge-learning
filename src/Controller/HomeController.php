<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(CartService $cartService): Response
    {
        $cartCount = $cartService->getCartItemCount();

        return $this->render('home/index.html.twig', [
            'cartCount' => $cartCount,
        ]);
    }
}
