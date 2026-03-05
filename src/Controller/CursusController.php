<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CursusRepository;
use App\Service\LessonAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CursusController extends AbstractController
{
    #[Route('/cursus/{id}', name: 'cursus_show', methods: ['GET'])]
    public function show(
        int $id,
        CursusRepository $cursusRepository,
        LessonAccessService $access
    ): Response {
        $cursus = $cursusRepository->findVisibleWithVisibleLessons($id);
        if (!$cursus) {
            throw $this->createNotFoundException('Cursus introuvable.');
        }

        $userHasAccess = [];
        $user = $this->getUser();

        if ($user instanceof User) {
            $userHasAccess = $access->getAccessibleLessonMapForCursus($user, $cursus);
        }

        return $this->render('cursus/show.html.twig', [
            'cursus' => $cursus,
            'userHasAccess' => $userHasAccess,
            'userHasCompleted' => [],
        ]);
    }
}