<?php

namespace App\Controller;

use App\Entity\Certification;
use App\Entity\LessonValidated;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Repository\LessonRepository;
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
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @return array{0: array<int,bool>, 1: array<int,bool>}
     */
    private function getUserAccessAndCompleted(): array
    {
        $user = $this->getUser();

        $userHasAccess = [];
        $userHasCompleted = [];

        if (!$user) {
            return [$userHasAccess, $userHasCompleted];
        }

        // Accès : items payés (leçon OU cursus)
        $paidItems = $this->em->getRepository(PurchaseItem::class)
            ->createQueryBuilder('pi')
            ->join('pi.purchase', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Purchase::STATUS_PAID)
            ->getQuery()
            ->getResult();

        foreach ($paidItems as $item) {
            if ($item->getLesson()) {
                $userHasAccess[$item->getLesson()->getId()] = true;
            }

            if ($item->getCursus()) {
                foreach ($item->getCursus()->getLessons() as $lesson) {
                    $userHasAccess[$lesson->getId()] = true;
                }
            }
        }

        // Complété : leçons validées
        $validatedLessons = $this->em->getRepository(LessonValidated::class)
            ->findBy(['user' => $user]);

        foreach ($validatedLessons as $validation) {
            $lessonId = $validation->getLesson()?->getId();
            if ($lessonId) {
                $userHasCompleted[$lessonId] = true;
            }
        }

        return [$userHasAccess, $userHasCompleted];
    }

    #[Route('/lesson/{id}', name: 'lesson_show', methods: ['GET'])]
    public function show(int $id, LessonRepository $lessonRepository): Response
    {
        $lesson = $lessonRepository->findVisibleLesson($id);
        if (!$lesson) {
            throw $this->createNotFoundException('Leçon introuvable.');
        }

        [$userHasAccess, $userHasCompleted] = $this->getUserAccessAndCompleted();
        $user = $this->getUser();

        $certification = null;
        if ($user) {
            $certification = $this->em->getRepository(Certification::class)->findOneBy([
                'user' => $user,
                'lesson' => $lesson,
                'type' => 'lesson',
            ]);
        }

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'userHasAccess' => $userHasAccess,
            'userHasCompleted' => $userHasCompleted,
            'certification' => $certification,
        ]);
    }

    #[Route('/lesson/{id}/complete', name: 'lesson_complete', methods: ['POST'])]
    public function complete(
        Request $request,
        int $id,
        LessonRepository $lessonRepository,
        LessonValidatedService $lessonService
    ): Response {
        $lesson = $lessonRepository->findVisibleLesson($id);
        if (!$lesson) {
            throw $this->createNotFoundException('Leçon introuvable.');
        }

        $tokenId = 'lesson_complete_' . $lesson->getId();
        $token = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid($tokenId, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Sécurité serveur : vérifier l’accès
        [$userHasAccess] = $this->getUserAccessAndCompleted();
        if (!isset($userHasAccess[$lesson->getId()])) {
            $this->addFlash('danger', "Tu n'as pas accès à cette leçon.");
            return $this->redirectToRoute('lesson_show', ['id' => $lesson->getId()]);
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $lessonService->validateLesson($user, $lesson);

        $this->addFlash('success', 'Leçon marquée comme complétée et certification générée !');

        return $this->redirectToRoute('lesson_show', ['id' => $lesson->getId()]);
    }
}