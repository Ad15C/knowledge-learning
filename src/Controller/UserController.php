<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Certification;
use App\Entity\Cursus;
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
use Dompdf\Dompdf;
use Dompdf\Options;

#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    #[Route('/dashboard', name: 'user_dashboard')]
    public function dashboard(
        PurchaseRepository $purchaseRepository,
        CertificationRepository $certificationRepository
    ): Response {
        $user = $this->getUser();
        $purchases = $purchaseRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $totalOrders = count($purchases);
        $totalSpent = $purchaseRepository->getTotalSpent($user);

        // Niveaux de fidélité
        $tiers = ['Bronze'=>0, 'Silver'=>100, 'Gold'=>300, 'Platinum'=>600];
        $status = 'Bronze'; $nextStatus = 'Silver'; $currentMin=0; $currentMax=100;
        foreach ($tiers as $tier => $minAmount) {
            if ($totalSpent >= $minAmount) { $status = $tier; $currentMin = $minAmount; }
            else { $nextStatus=$tier; $currentMax=$minAmount; break; }
        }
        $progressPercent = $currentMax>$currentMin ? min(100, round((($totalSpent-$currentMin)/($currentMax-$currentMin))*100)) : 0;

        $certifications = $certificationRepository->findBy(['user'=>$user]);
        $certificationsCount = $certificationRepository->countByUser($user);
        $latestPurchases = array_slice($purchases, 0, 5);

        return $this->render('user/dashboard.html.twig', [
            'user' => $user,
            'orders' => $latestPurchases,
            'totalOrders' => $totalOrders,
            'totalSpent' => $totalSpent,
            'status' => $status,
            'nextStatus' => $nextStatus,
            'progressPercent' => $progressPercent,
            'certifications' => $certifications,
            'certificationsCount' => $certificationsCount,
        ]);
    }

    #[Route('/dashboard/edit', name: 'user_dashboard_edit')]
    public function editProfile(Request $request, EntityManagerInterface $em): Response
    {
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
        'back_path' => $this->generateUrl('user_dashboard'),
        'title' => 'Modifier mon profil',
        'button_label' => 'Mettre à jour',
        ]);
    }

    #[Route('/dashboard/password', name: 'app_change_password')]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $em ): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(\App\Form\ChangePasswordFormType::class);
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
        $user = $this->getUser();
        $status = $request->query->get('status');
        $fromDate = $request->query->get('from');
        $toDate = $request->query->get('to');

        $repo = $em->getRepository('App\Entity\Purchase');
        $qb = $repo->createQueryBuilder('p')->andWhere('p.user = :user')->setParameter('user', $user);

        if ($status) $qb->andWhere('p.status = :status')->setParameter('status', $status);
        if ($fromDate) $qb->andWhere('p.createdAt >= :from')->setParameter('from', new \DateTime($fromDate));
        if ($toDate) $qb->andWhere('p.createdAt <= :to')->setParameter('to', new \DateTime($toDate));

        $purchases = $qb->orderBy('p.createdAt', 'DESC')->getQuery()->getResult();

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
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $filterCursusId = $request->query->get('cursus');
        $filterFrom = $request->query->get('from');
        $filterTo = $request->query->get('to');

        $certRepo = $em->getRepository(\App\Entity\Certification::class);
        $cursusRepo = $em->getRepository(\App\Entity\Cursus::class);
        $allCursus = $cursusRepo->findAll();

        // Construction du QueryBuilder pour filtrer les certifications
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
            $qb->andWhere('c.issuedAt >= :from')
            ->setParameter('from', new \DateTime($filterFrom));
        }

        if ($filterTo) {
            $qb->andWhere('c.issuedAt <= :to')
            ->setParameter('to', new \DateTime($filterTo));
        }

        $certifications = $qb->orderBy('c.issuedAt', 'DESC')
                            ->getQuery()
                            ->getResult();

        return $this->render('user/certifications.html.twig', [
            'certifications' => $certifications,
            'all_cursus' => $allCursus,
            'filter_cursus' => $filterCursusId,
            'filter_from' => $filterFrom,
            'filter_to' => $filterTo,
        ]);
    }

    #[Route('/dashboard/certification/{id}', name: 'certification_show')]
    public function show(Certification $certification): Response
    {
        $user = $this->getUser();
        if ($certification->getUser()->getId() !== $user->getId()) {
            $this->addFlash('warning', 'Vous n’êtes pas autorisé à voir cette certification.');
            return $this->redirectToRoute('user_dashboard_certifications');
        }

        return $this->render('user/certification_show.html.twig', [
            'certification' => $certification,
        ]);
    }

    #[Route('/dashboard/certification/{id}/pdf', name: 'app_certification_pdf', methods: ['GET'])]
    public function certificationPdf(Certification $certification): Response
    {
        $user = $this->getUser();

        if ($certification->getUser()->getId() !== $user->getId()) {
            $this->addFlash('danger', 'Vous n’êtes pas autorisé à accéder à ce certificat.');
            return $this->redirectToRoute('user_dashboard_certifications');
        }

        $html = $this->renderView('user/certification_pdf.html.twig', [
            'cert' => $certification,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificat-'.$certification->getCertificateCode().'.pdf"',
        ]);
    }

    #[Route('/validate/{lessonId}', name: 'validate_lesson')]
    public function validateLesson(int $lessonId): Response
    {
        // Redirection vers la vraie route de validation dans LessonController
        return $this->redirectToRoute('lesson_validate', ['lessonId' => $lessonId]);
    }
}