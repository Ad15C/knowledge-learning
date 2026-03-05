<?php

namespace App\Controller;

use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ThemeController extends AbstractController
{
    #[Route('/themes', name: 'themes_index', methods: ['GET'])]
    public function index(Request $request, ThemeRepository $themeRepository): Response
    {
        $filterName = $request->query->get('name');
        $filterMinPrice = $request->query->get('minPrice');
        $filterMaxPrice = $request->query->get('maxPrice');

        $min = ($filterMinPrice === null || $filterMinPrice === '') ? null : (float) $filterMinPrice;
        $max = ($filterMaxPrice === null || $filterMaxPrice === '') ? null : (float) $filterMaxPrice;

        $themes = $themeRepository->findVisibleThemesWithFilters($filterName, $min, $max);

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

    #[Route('/themes/{id}', name: 'theme_show', methods: ['GET'])]
    public function show(int $id, ThemeRepository $themeRepository): Response
    {
        $theme = $themeRepository->findVisibleTheme($id);

        if (!$theme) {
            throw $this->createNotFoundException('Thème introuvable.');
        }

        return $this->render('themes/show.html.twig', [
            'theme' => $theme,
        ]);
    }
}