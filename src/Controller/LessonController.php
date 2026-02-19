<?php

namespace App\Controller;

use App\Entity\Lesson;
use App\Entity\PurchaseItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/lesson')]
class LessonController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Afficher une leçon si elle a été achetée
     */
    #[Route('/{id}', name: 'lesson_show')]
    public function show(Lesson $lesson): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('warning', 'Vous devez être connecté pour accéder à cette leçon.');
            return $this->redirectToRoute('app_login');
        }

        // Vérifier si la leçon a été achetée
        $items = $this->em->getRepository(PurchaseItem::class)->findBy([
            'lesson' => $lesson
        ]);

        $hasAccess = false;
        foreach ($items as $item) {
            $purchase = $item->getPurchase();
            if ($purchase->getUser() === $user && $purchase->getStatus() === 'paid') {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            $this->addFlash('warning', 'Vous devez acheter ce cours pour y accéder.');
            return $this->redirectToRoute('themes_index');
        }

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson
        ]);
    }

    /**
     * Valider une leçon pour générer la certification
     */
    #[Route('/validate/{id}', name: 'lesson_validate')]
    public function validate(Lesson $lesson): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('warning', 'Vous devez être connecté pour valider une leçon.');
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que l’utilisateur a accès à la leçon
        $items = $this->em->getRepository(PurchaseItem::class)->findBy([
            'lesson' => $lesson
        ]);

        $hasAccess = false;
        foreach ($items as $item) {
            $purchase = $item->getPurchase();
            if ($purchase->getUser() === $user && $purchase->getStatus() === 'paid') {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            $this->addFlash('warning', 'Vous ne pouvez pas valider une leçon que vous n’avez pas achetée.');
            return $this->redirectToRoute('themes_index');
        }

        // Marquer la leçon comme validée pour cet utilisateur
        $lesson->setUserHasCompleted(true);
        $this->em->flush();

        $this->addFlash('success', 'Leçon validée !');
        return $this->redirectToRoute('lesson_show', ['id' => $lesson->getId()]);
    }
}
