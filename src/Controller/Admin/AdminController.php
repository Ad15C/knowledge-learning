<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    #[Route('/admin/themes', name: 'admin_theme_hub')]
    public function themeHub(): Response
    {
        return $this->render('admin/theme/hub.html.twig');
    }

    #[Route('/admin/cursus', name: 'admin_cursus_hub')]
    public function cursusHub(): Response
    {
        return $this->render('admin/cursus/hub.html.twig');
    }

    #[Route('/admin/lessons', name: 'admin_lesson_hub')]
    public function lessonHub(): Response
    {
        return $this->render('admin/lesson/hub.html.twig');
    }
}