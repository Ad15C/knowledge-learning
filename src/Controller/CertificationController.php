<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/certification')]
class CertificationController extends AbstractController
{
    #[Route('/', name: 'certification_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les certifications obtenues (à partir des leçons validées)
        // Ici exemple fictif
        $certifications = []; 

        return $this->render('certification/index.html.twig', [
            'certifications' => $certifications,
        ]);
    }

    #[Route('/{id}', name: 'certification_show')]
    public function show(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer la certification par id et vérifier qu'elle appartient à l'utilisateur
        $certification = null; // remplacer par logique réelle

        if (!$certification) {
            $this->addFlash('warning', 'Certification introuvable.');
            return $this->redirectToRoute('certification_index');
        }

        return $this->render('certification/show.html.twig', [
            'certification' => $certification,
        ]);
    }
}
