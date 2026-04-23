<?php

namespace App\Controller;

use App\Entity\Certification;
use App\Entity\LessonValidated;
use App\Entity\User;
use App\Repository\LessonRepository;
use App\Service\LessonAccessService;
use App\Service\LessonValidatedService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class LessonController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private LessonAccessService $access
    ) {
    }

    /**
     * @return array<int,bool>
     */
    private function getUserCompletedLessonMap(User $user): array
    {
        $out = [];

        $validated = $this->em->getRepository(LessonValidated::class)->findBy([
            'user' => $user,
            'completed' => true,
        ]);

        foreach ($validated as $validation) {
            $id = $validation->getLesson()?->getId();
            if ($id !== null) {
                $out[$id] = true;
            }
        }

        return $out;
    }

    #[Route('/lesson/{slug}', name: 'lesson_show', methods: ['GET'])]
    public function show(string $slug, LessonRepository $lessonRepository): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $lesson = $lessonRepository->findVisibleLessonBySlug($slug);

        if (!$lesson) {
            throw $this->createNotFoundException('Leçon introuvable.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->access->userCanAccessLesson($user, $lesson)) {
            $this->addFlash('danger', "Vous ne pouvez pas accéder à cette leçon pour le moment.");
            return $this->redirectToRoute('cursus_show', [
                'slug' => $lesson->getCursus()?->getSlug(),
            ]);
        }

        $userHasCompleted = $this->getUserCompletedLessonMap($user);

        $certification = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'userHasAccess' => [$lesson->getId() => true],
            'userHasCompleted' => $userHasCompleted,
            'certification' => $certification,
        ]);
    }

    #[Route('/lesson/{slug}/complete', name: 'lesson_complete', methods: ['POST'])]
    public function complete(
        Request $request,
        string $slug,
        LessonRepository $lessonRepository,
        LessonValidatedService $lessonService
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $lesson = $lessonRepository->findVisibleLessonBySlug($slug);

        if (!$lesson) {
            throw $this->createNotFoundException('Leçon introuvable.');
        }

        $tokenId = 'lesson_complete_' . $lesson->getId();
        $token = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid($tokenId, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->access->userCanAccessLesson($user, $lesson)) {
            $this->addFlash('danger', "Vous ne pouvez pas accéder à cette leçon pour le moment.");
            return $this->redirectToRoute('cursus_show', [
                'slug' => $lesson->getCursus()?->getSlug(),
            ]);
        }

        $lessonService->validateLesson($user, $lesson);

        $this->addFlash('success', 'Félicitations ! Vous avez validé cette leçon. Votre certificat est désormais disponible.');

        return $this->redirectToRoute('lesson_show', [
            'slug' => $lesson->getSlug(),
        ]);
    }
}