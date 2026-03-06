<?php

namespace App\Controller\Admin;

use App\Entity\Theme;
use App\Form\ThemeType;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/themes', name: 'admin_theme_')]
class AdminThemeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ThemeRepository $repo): Response
    {
        $q = $request->query->get('q');
        $status = $request->query->get('status', 'all');
        $sort = $request->query->get('sort', 'created_desc');

        $requireCursus = $status === 'active';

        $themesWithVisibility = $repo->findAdminThemesWithVisibility(
            $q,
            $status,
            $sort,
            true,
            $requireCursus
        );

        return $this->render('admin/theme/index.html.twig', [
            'themesWithVisibility' => $themesWithVisibility,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'sort' => $sort,
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $theme = new Theme();
        $theme->setIsActive(true);

        $form = $this->createForm(ThemeType::class, $theme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($theme);
            $em->flush();

            $this->addFlash('success', 'Thème créé.');
            return $this->redirectToRoute('admin_theme_index');
        }

        return $this->render('admin/theme/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Theme $theme, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ThemeType::class, $theme);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Thème modifié.');
            return $this->redirectToRoute('admin_theme_index');
        }

        return $this->render('admin/theme/edit.html.twig', [
            'theme' => $theme,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function deleteConfirm(Theme $theme): Response
    {
        return $this->render('admin/theme/delete.html.twig', [
            'theme' => $theme,
        ]);
    }

    #[Route('/{id}/disable', name: 'disable', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function disable(Theme $theme, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('theme_disable'.$theme->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $theme->setIsActive(false);
        $em->flush();

        $this->addFlash('success', 'Thème désactivé.');
        return $this->redirectToRoute('admin_theme_index');
    }

    #[Route('/{id}/activate', name: 'activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function activate(Theme $theme, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('theme_activate'.$theme->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $theme->setIsActive(true);
        $em->flush();

        $this->addFlash('success', 'Thème réactivé.');
        return $this->redirectToRoute('admin_theme_index');
    }
}