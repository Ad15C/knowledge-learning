<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Purchase;
use App\Entity\Certification;
use App\Form\UserProfileFormType;
use App\Form\ChangePasswordFormType;
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
    public function dashboard(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // récupérer les achats de l'utilisateur
        $purchases = $em->getRepository(Purchase::class)
            ->findBy(['user' => $user], ['createdAt' => 'DESC']);

        // calculer le total dépensé
        $totalSpent = 0;
        foreach ($purchases as $purchase) {
            $totalSpent += $purchase->getTotal();
        }

        // nombre total de commandes
        $totalOrders = count($purchases);

        // progression globale (simple exemple basé sur nb commandes)
        $progression = min(100, $totalOrders * 10);

        // ==========================
        // Statut utilisateur & progression vers le prochain niveau
        // ==========================

        $status = 'Bronze';
        $nextStatus = 'Silver';
        $progressPercent = 0;

        // seuils
        $tiers = [
            'Bronze' => 0,
            'Silver' => 100,
            'Gold' => 300,
            'Platinum' => 600
        ];

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

        if ($currentMax > $currentMin) {
            $progressPercent = (($totalSpent - $currentMin) / ($currentMax - $currentMin)) * 100;
            $progressPercent = min(100, round($progressPercent));
        }

        // ==========================
        // Envoi des données à Twig
        // ==========================
        return $this->render('user/dashboard.html.twig', [
            'user' => $user,
            'orders' => array_slice($purchases, 0, 5), // 5 derniers achats
            'totalSpent' => $totalSpent,
            'totalOrders' => $totalOrders,
            'progression' => $progression,
            'status' => $status,
            'nextStatus' => $nextStatus,
            'progressPercent' => $progressPercent,
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
            'profileForm' => $form->createView()
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
            $user->setPassword(
                $passwordHasher->hashPassword($user, $newPassword)
            );

            $em->flush();
            $this->addFlash('success', 'Mot de passe mis à jour !');

            return $this->redirectToRoute('user_dashboard');
        }

        return $this->render('user/change_password.html.twig', [
            'passwordForm' => $form->createView()
        ]);
    }

    #[Route('/dashboard/purchases', name: 'user_dashboard_purchases')]
    public function purchases(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $purchases = $this->getDoctrine()
            ->getRepository(Purchase::class)
            ->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('user/purchases.html.twig', [
            'purchases' => $purchases
        ]);
    }

    #[Route('/dashboard/certifications', name: 'user_dashboard_certifications')]
    public function certifications(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $certifications = $this->getDoctrine()
            ->getRepository(Certification::class)
            ->findBy(['user' => $user], ['issuedAt' => 'DESC']);

        return $this->render('user/certifications.html.twig', [
            'certifications' => $certifications
        ]);
    }
}
