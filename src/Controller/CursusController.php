<?php

namespace App\Controller;

use App\Entity\LessonValidated;
use App\Entity\User;
use App\Repository\CursusRepository;
use App\Service\LessonAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CursusController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
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
            $lessonId = $validation->getLesson()?->getId();
            if ($lessonId !== null) {
                $out[$lessonId] = true;
            }
        }

        return $out;
    }

    #[Route('/cursus/{slug}', name: 'cursus_show', methods: ['GET'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function show(
        string $slug,
        CursusRepository $cursusRepository,
        LessonAccessService $access
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $cursus = $cursusRepository->findVisibleWithVisibleLessonsBySlug($slug);

        if (!$cursus) {
            throw $this->createNotFoundException('Cursus introuvable.');
        }

        $userHasAccess = [];
        $userHasCompleted = [];
        $user = $this->getUser();

        if ($user instanceof User) {
            $userHasAccess = $access->getAccessibleLessonMapForCursus($user, $cursus);
            $userHasCompleted = $this->getUserCompletedLessonMap($user);
        }

        return $this->render('cursus/show.html.twig', [
            'cursus' => $cursus,
            'userHasAccess' => $userHasAccess,
            'userHasCompleted' => $userHasCompleted,
        ]);
    }
}