<?php

namespace App\Controller;

use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\PurchaseItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LessonController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    private function getUserAccessAndCompleted(): array
    {
        $user = $this->getUser();
        $userHasAccess = [];
        $userHasCompleted = [];

        if ($user) {
            // Récupérer les items payés (leçons + cursus)
            $paidItems = $this->em->getRepository(PurchaseItem::class)
                ->createQueryBuilder('pi')
                ->join('pi.purchase', 'p')
                ->andWhere('p.user = :user')
                ->andWhere('p.status = :status')
                ->setParameters([
                    'user' => $user,
                    'status' => 'paid'
                ])
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

            // Leçons déjà validées
            $validatedLessons = $this->em->getRepository(LessonValidated::class)
                ->findBy(['user' => $user]);

            foreach ($validatedLessons as $validation) {
                $userHasCompleted[$validation->getLesson()->getId()] = true;
            }
        }

        return [$userHasAccess, $userHasCompleted];
    }

    #[Route('/lesson/{id}', name: 'lesson_show')]
    public function show(Lesson $lesson): Response
    {
        [$userHasAccess, $userHasCompleted] = $this->getUserAccessAndCompleted();

        // Vérifier accès
        if (!isset($userHasAccess[$lesson->getId()])) {
            throw $this->createAccessDeniedException('Vous devez acheter cette leçon ou le cursus.');
        }

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'userHasAccess' => $userHasAccess,
            'userHasCompleted' => $userHasCompleted
        ]);
    }

    #[Route('/lesson/validate/{id}', name: 'lesson_validate')]
    public function validate(Lesson $lesson): Response
    {
        [$userHasAccess, $userHasCompleted] = $this->getUserAccessAndCompleted();

        if (!isset($userHasAccess[$lesson->getId()])) {
            throw $this->createAccessDeniedException('Vous devez acheter cette leçon ou le cursus.');
        }

        $user = $this->getUser();

        if (!isset($userHasCompleted[$lesson->getId()])) {
            $validation = new LessonValidated();
            $validation->setUser($user)
                       ->setLesson($lesson);

            $this->em->persist($validation);
            $this->em->flush();

            $this->addFlash('success', 'Leçon validée avec succès !');
        } else {
            $this->addFlash('info', 'Vous avez déjà validé cette leçon.');
        }

        return $this->redirectToRoute('lesson_show', ['id' => $lesson->getId()]);
    }
}