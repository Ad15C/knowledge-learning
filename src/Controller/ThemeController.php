<?php

namespace App\Controller;

use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ThemeController extends AbstractController
{
    #[Route('/themes', name: 'themes_index')]
    public function index(Request $request, ThemeRepository $themeRepository): Response
    {
        $filterName = $request->query->get('name');
        $filterMinPrice = $request->query->get('minPrice');
        $filterMaxPrice = $request->query->get('maxPrice');

        $themes = $themeRepository->findThemesWithFilters(
            $filterName,
            $filterMinPrice !== null ? (float)$filterMinPrice : null,
            $filterMaxPrice !== null ? (float)$filterMaxPrice : null
        );

        // Appel AJAX -> retourne seulement le fragment HTML
        if ($request->isXmlHttpRequest()) {
            return $this->render('themes/_themes_list.html.twig', [
                'themes' => $themes,
            ]);
        }

        return $this->render('themes/index.html.twig', [
            'themes' => $themes,
            'filter_name' => $filterName,
            'filter_minPrice' => $filterMinPrice,
            'filter_maxPrice' => $filterMaxPrice,
        ]);
    }

    #[Route('/themes/{id}', name: 'theme_show')]
    public function show(int $id, ThemeRepository $themeRepository): Response
    {
        $theme = $themeRepository->find($id);

        if (!$theme) {
            throw $this->createNotFoundException('Thème introuvable.');
        }

        return $this->render('themes/show.html.twig', [
            'theme' => $theme,
        ]);
    }
}