<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Lesson;
use App\Entity\Cursus;
use App\Entity\Theme;
use App\Entity\Purchase;
use App\Entity\Certification;
use App\Form\UserProfileFormType;
use App\Form\ChangePasswordFormType;
use App\Repository\PurchaseRepository;
use App\Repository\CertificationRepository;
use App\Repository\LessonRepository;
use App\Service\LessonValidatedService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    #[Route('/dashboard', name: 'user_dashboard')]
    public function dashboard(
        PurchaseRepository $purchaseRepository,
        CertificationRepository $certificationRepository
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $purchases = $purchaseRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $totalOrders = count($purchases);
        $totalSpent = $purchaseRepository->getTotalSpent($user);

        // Niveaux de fidélité
        $tiers = [
            'Bronze' => 0,
            'Silver' => 100,
            'Gold' => 300,
            'Platinum' => 600,
        ];

        $status = 'Bronze';
        $nextStatus = 'Silver';
        $currentMin = 0;
        $currentMax = 100;

        foreach ($tiers as $tier => $minAmount) {
            if ($totalSpent >= $minAmount) {
                $status = $tier;
                $currentMin = $minAmount;
            } else {
                $nextStatus = $tier;
                $currentMax = $minAmount;
                break;
            }
        }

        $progressPercent = $currentMax > $currentMin
            ? min(100, round((($totalSpent - $currentMin) / ($currentMax - $currentMin)) * 100))
            : 0;

        $certifications = $certificationRepository->findBy(['user' => $user]);
        $certificationsCount = count($certifications);

        // Limiter aux 5 dernières commandes
        $latestPurchases = array_slice($purchases, 0, 5);

        return $this->render('user/dashboard.html.twig', [
            'user' => $user,
            'orders' => $latestPurchases,
            'totalOrders' => $totalOrders,
            'totalSpent' => $totalSpent,
            'status' => $status,
            'nextStatus' => $nextStatus,
            'progressPercent' => $progressPercent,
            'certificationsCount' => $certificationsCount,
        ]);
    }

    #[Route('/dashboard/edit', name: 'user_dashboard_edit')]
    public function editProfile(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserProfileFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('user_dashboard');
        }

        return $this->render('user/edit.html.twig', [
            'editProfileForm' => $form->createView(),
        ]);
    }

    #[Route('/dashboard/password', name: 'user_dashboard_password')]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));

            $em->flush();
            $this->addFlash('success', 'Mot de passe mis à jour !');

            return $this->redirectToRoute('user_dashboard');
        }

        return $this->render('user/change_password.html.twig', [
            'passwordForm' => $form->createView(),
        ]);
    }

    #[Route('/dashboard/purchases', name: 'user_dashboard_purchases')]
    public function purchases(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $status = $request->query->get('status');
        $fromDate = $request->query->get('from');
        $toDate = $request->query->get('to');

        $repo = $em->getRepository(Purchase::class);
        $qb = $repo->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user);

        if ($status) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        if ($fromDate) {
            $from = new \DateTime($fromDate);
            $qb->andWhere('p.createdAt >= :from')->setParameter('from', $from);
        }

        if ($toDate) {
            $to = new \DateTime($toDate);
            $qb->andWhere('p.createdAt <= :to')->setParameter('to', $to);
        }

        $purchases = $qb->orderBy('p.createdAt', 'DESC')->getQuery()->getResult();

        // --- Calcul du premier cours non complété pour chaque cursus ---
        foreach ($purchases as $purchase) {
            foreach ($purchase->getItems() as $item) {
                if ($item->getCursus()) {
                    $completedLessonIds = $user->getCompletedLessons()->map(fn($l) => $l->getId())->toArray();
                    $firstIncompleteLesson = null;

                    // On cherche la première leçon non complétée
                    foreach ($item->getCursus()->getLessons() as $lesson) {
                        if (!in_array($lesson->getId(), $completedLessonIds)) {
                            $firstIncompleteLesson = $lesson;
                            break;
                        }
                    }

                    // Si tout est complété, on prend la première leçon
                    if (!$firstIncompleteLesson) {
                        $firstIncompleteLesson = $item->getCursus()->getLessons()->first();
                    }

                    // On ajoute une propriété temporaire pour Twig
                    $item->firstIncompleteLesson = $firstIncompleteLesson;
                }
            }
        }

        return $this->render('user/purchases.html.twig', [
            'purchases' => $purchases,
            'filter_status' => $status,
            'filter_from' => $fromDate,
            'filter_to' => $toDate,
        ]);
    }

    #[Route('/dashboard/certifications', name: 'user_dashboard_certifications')]
    public function certifications(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $filterCursusId = $request->query->get('cursus');
        $filterFrom = $request->query->get('from');
        $filterTo = $request->query->get('to');

        $certRepo = $em->getRepository(Certification::class);
        $cursusRepo = $em->getRepository(Cursus::class);

        $allCursus = $cursusRepo->findAll();

        $qb = $certRepo->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user);

        if ($filterCursusId) {
            $cursus = $cursusRepo->find($filterCursusId);
            if ($cursus) {
                $qb->andWhere('c.cursus = :cursus')
                   ->setParameter('cursus', $cursus);
            }
        }

        if ($filterFrom) {
            $from = new \DateTime($filterFrom);
            $qb->andWhere('c.issuedAt >= :from')->setParameter('from', $from);
        }

        if ($filterTo) {
            $to = new \DateTime($filterTo);
            $qb->andWhere('c.issuedAt <= :to')->setParameter('to', $to);
        }

        $certifications = $qb->orderBy('c.issuedAt', 'DESC')->getQuery()->getResult();

        return $this->render('user/certifications.html.twig', [
            'certifications' => $certifications,
            'all_cursus' => $allCursus,
            'filter_cursus' => $filterCursusId,
            'filter_from' => $filterFrom,
            'filter_to' => $filterTo,
        ]);
    }

    #[Route('/validate/{lessonId}', name: 'validate')]
    public function validateLesson(
        int $lessonId,
        LessonValidatedService $lessonService,
        LessonRepository $lessonRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $lesson = $lessonRepository->find($lessonId);
        if (!$lesson) {
            $this->addFlash('error', 'Leçon introuvable.');
            return $this->redirectToRoute('user_dashboard');
        }

        $validation = $lessonService->validateLesson($user, $lesson);

        // Vérifier le theme pour éviter null
        $theme = $lesson->getCursus()?->getTheme();
        $allCompleted = $theme ? $lessonService->isThemeCompleted($user, $theme) : false;

        $this->addFlash('success', 'Leçon validée !');

        return $this->render('lesson/validated.html.twig', [
            'lessonValidated' => $validation,
            'allCompleted' => $allCompleted,
        ]);
    }
}
