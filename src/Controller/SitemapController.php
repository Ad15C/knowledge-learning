<?php

namespace App\Controller;

use App\Repository\ThemeRepository;
use App\Repository\CursusRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SitemapController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'sitemap', methods: ['GET'])]
    public function sitemap(
        ThemeRepository $themeRepository,
        CursusRepository $cursusRepository
    ): Response {
        $staticUrls = [
            ['route' => 'homepage'],
            ['route' => 'themes_index'],
            ['route' => 'contact_index'],
            ['route' => 'privacy_policy'],
            ['route' => 'legal_notice'],
            ['route' => 'cookies_policy'],
            ['route' => 'cgv_policy'],
        ];

        $themes = $themeRepository->findAll();
        $cursus = $cursusRepository->findAll();

        $xml = $this->renderView('seo/sitemap.xml.twig', [
            'staticUrls' => $staticUrls,
            'themes' => $themes,
            'cursus' => $cursus,
        ]);

        return new Response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}