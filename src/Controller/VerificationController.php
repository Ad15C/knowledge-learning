<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VerificationController extends AbstractController
{
    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verify(Request $request, EntityManagerInterface $em): Response
    {
        $token = $request->query->get('token');
        if (!$token) {
            $this->addFlash('error', 'Token manquant.');
            return $this->redirectToRoute('app_login');
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->getVerificationTokenExpiresAt() && $user->getVerificationTokenExpiresAt() < new \DateTimeImmutable()) {
            $this->addFlash('error', 'Token expiré.');
            return $this->redirectToRoute('app_login');
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $user->setVerificationTokenExpiresAt(null);

        $em->flush();

        $this->addFlash('success', 'Compte vérifié. Vous pouvez vous connecter.');
        $request->getSession()->remove('dev_verify_url');

        return $this->redirectToRoute('app_login');
    }
}