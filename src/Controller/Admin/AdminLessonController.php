<?php

namespace App\Controller\Admin;

use App\Entity\Lesson;
use App\Form\LessonType;
use App\Repository\LessonRepository;
use App\Repository\CursusRepository;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/lesson', name: 'admin_lesson_')]
class AdminLessonController extends AbstractController
{
    // 1) Liste
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, LessonRepository $repo, CursusRepository $cursusRepo, ThemeRepository $themeRepo): Response
    {
        $q = $request->query->get('q');
        $status = $request->query->get('status', 'all'); // all|active|archived
        $cursusId = $request->query->getInt('cursus', 0) ?: null;
        $themeId = $request->query->getInt('theme', 0) ?: null;
        $sort = $request->query->get('sort', 'id_desc');

        $lessons = $repo->createAdminFilterQueryBuilder($q, $status, $cursusId, $themeId, $sort)
            ->getQuery()
            ->getResult();

        return $this->render('admin/lesson/index.html.twig', [
            'lessons' => $lessons,
            'cursus_list' => $cursusRepo->findBy([], ['name' => 'ASC']),
            'themes' => $themeRepo->findBy([], ['name' => 'ASC']),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'cursus' => $cursusId,
                'theme' => $themeId,
                'sort' => $sort,
            ],
        ]);
    }

    // 2) Créer
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $lesson = new Lesson();
        $lesson->setIsActive(true);

        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lesson);
            $em->flush();

            $this->addFlash('success', 'Leçon créée.');
            return $this->redirectToRoute('admin_lesson_index');
        }

        return $this->render('admin/lesson/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // 3) Modifier
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Lesson $lesson, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Leçon modifiée.');
            return $this->redirectToRoute('admin_lesson_index');
        }

        return $this->render('admin/lesson/edit.html.twig', [
            'lesson' => $lesson,
            'form' => $form->createView(),
        ]);
    }

    // 4) Page confirmation (delete = archive)
    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function deleteConfirm(Lesson $lesson): Response
    {
        return $this->render('admin/lesson/delete.html.twig', [
            'lesson' => $lesson,
        ]);
    }

    // 5) Action POST archiver
    #[Route('/{id}/disable', name: 'disable', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function disable(Lesson $lesson, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('lesson_disable'.$lesson->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $lesson->setIsActive(false);
        $em->flush();

        $this->addFlash('success', 'Leçon archivée.');
        return $this->redirectToRoute('admin_lesson_index');
    }

    // 6) Action POST restaurer
    #[Route('/{id}/activate', name: 'activate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function activate(Lesson $lesson, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('lesson_activate'.$lesson->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $lesson->setIsActive(true);
        $em->flush();

        $this->addFlash('success', 'Leçon restaurée.');
        return $this->redirectToRoute('admin_lesson_index');
    }
}