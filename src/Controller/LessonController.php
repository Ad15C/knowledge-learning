<?php

namespace App\Controller;

use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\Certification;
use App\Service\LessonValidatedService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class LessonController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Récupère les leçons accessibles et déjà complétées pour l'utilisateur
     */
    private function getUserAccessAndCompleted(): array
    {
        $user = $this->getUser();
        $userHasAccess = [];
        $userHasCompleted = [];

        if (!$user) {
            return [$userHasAccess, $userHasCompleted];
        }

        $paidItems = $this->em->getRepository('App\Entity\PurchaseItem')
            ->createQueryBuilder('pi')
            ->join('pi.purchase', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'paid')
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

        $validatedLessons = $this->em->getRepository(LessonValidated::class)
            ->findBy(['user' => $user]);

        foreach ($validatedLessons as $validation) {
            $userHasCompleted[$validation->getLesson()->getId()] = true;
        }

        return [$userHasAccess, $userHasCompleted];
    }

    #[Route('/lesson/{id}', name: 'lesson_show')]
    public function show(Lesson $lesson): Response
    {
        if (!$lesson->isPubliclyAccessible()) {
            throw $this->createNotFoundException('Leçon introuvable.');
        }

        [$userHasAccess, $userHasCompleted] = $this->getUserAccessAndCompleted();
        $user = $this->getUser();

        $certification = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'userHasAccess' => $userHasAccess,
            'userHasCompleted' => $userHasCompleted,
            'certification' => $certification,
        ]);
    }

    #[Route('/lesson/{id}/complete', name: 'lesson_complete', methods: ['POST'])]
    public function complete(Lesson $lesson, LessonValidatedService $lessonService): Response
    {
        if (!$lesson->isPubliclyAccessible()) {
            throw $this->createNotFoundException('Leçon introuvable.');
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $lessonService->validateLesson($user, $lesson);

        $this->addFlash('success', 'Leçon marquée comme complétée et certification générée !');

        return $this->redirectToRoute('lesson_show', ['id' => $lesson->getId()]);
    }
}