<?php

namespace App\Controller;

use App\Service\CartService;
use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(CartService $cartService, ThemeRepository $themeRepo): Response
    {
        // Récupérer tous les thèmes pour les afficher sur la page d'accueil
        $themes = $themeRepo->findAll();

        return $this->render('home/index.html.twig', [
            'cartCount' => $cartService->getCartItemCount(),
            'themes' => $themes, // passe les thèmes au template
        ]);
    }
}
