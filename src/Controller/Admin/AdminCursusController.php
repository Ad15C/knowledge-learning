<?php

namespace App\Controller\Admin;

use App\Entity\Cursus;
use App\Form\CursusType;
use App\Repository\CursusRepository;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/cursus', name: 'admin_cursus_')]
class AdminCursusController extends AbstractController
{
    // 1) Liste
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, CursusRepository $repo, ThemeRepository $themeRepo): Response
    {
        $q = $request->query->get('q');
        $status = $request->query->get('status', 'all'); // all|active|archived
        $themeId = $request->query->getInt('theme', 0) ?: null;
        $sort = $request->query->get('sort', 'id_desc');

        $cursusList = $repo->createAdminFilterQueryBuilder($q, $status, $themeId, $sort)
            ->getQuery()
            ->getResult();

        // pour remplir le select des thèmes
        $themes = $themeRepo->findBy([], ['name' => 'ASC']);

        return $this->render('admin/cursus/index.html.twig', [
            'cursus_list' => $cursusList,
            'themes' => $themes,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'theme' => $themeId,
                'sort' => $sort,
            ],
        ]);
    }

    // 2) Créer
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ThemeRepository $themeRepo): Response
    {
        $activeThemesCount = (int) $themeRepo->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();

        $hasActiveThemes = $activeThemesCount > 0;

        $cursus = new Cursus();
        $cursus->setIsActive(true);

        $form = $this->createForm(CursusType::class, $cursus);
        $form->handleRequest($request);

        // Sécurité serveur : si pas de thème actif et POST manuel => redirect + flash
        if (!$hasActiveThemes && $request->isMethod('POST')) {
            $this->addFlash('error', 'Aucun thème actif disponible. Crée ou réactive un thème avant de créer un cursus.');
            return $this->redirectToRoute('admin_theme_index');
        }

        if ($hasActiveThemes && $form->isSubmitted() && $form->isValid()) {
            $em->persist($cursus);
            $em->flush();

            $this->addFlash('success', 'Cursus créé.');
            return $this->redirectToRoute('admin_cursus_index');
        }

        return $this->render('admin/cursus/new.html.twig', [
            'form' => $form->createView(),
            'has_active_themes' => $hasActiveThemes,
        ]);
    }

    // 3) Modifier
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Cursus $cursus, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CursusType::class, $cursus);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Cursus modifié.');
            return $this->redirectToRoute('admin_cursus_index');
        }

        return $this->render('admin/cursus/edit.html.twig', [
            'cursus' => $cursus,
            'form' => $form->createView(),
        ]);
    }

    // 4) Page de confirmation "delete" (= archiver)
    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function deleteConfirm(Cursus $cursus): Response
    {
        return $this->render('admin/cursus/delete.html.twig', [
            'cursus' => $cursus,
        ]);
    }

    // 5) Action POST archiver (= disable)
    #[Route('/{id}/disable', name: 'disable', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function disable(Cursus $cursus, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('cursus_disable'.$cursus->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $cursus->setIsActive(false);
        $em->flush();

        $this->addFlash('success', 'Cursus archivé.');
        return $this->redirectToRoute('admin_cursus_index');
    }

    // 6) Action POST réactiver
    #[Route('/{id}/activate', name: 'activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function activate(Cursus $cursus, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('cursus_activate'.$cursus->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $cursus->setIsActive(true);
        $em->flush();

        $this->addFlash('success', 'Cursus réactivé.');
        return $this->redirectToRoute('admin_cursus_index');
    }
}