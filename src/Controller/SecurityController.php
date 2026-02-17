<?php

namespace App\Controller;

use App\Form\LoginFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;



class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Récupère l'erreur de connexion (si elle existe)
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        // Crée le formulaire de login
        $loginForm = $this->createForm(LoginFormType::class);

        return $this->render('security/login.html.twig', [
            'loginForm' => $loginForm->createView(),
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }


    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // cette méthode peut rester vide, Symfony s'occupe de la déconnexion
        throw new \LogicException('Cette méthode peut rester vide.');
    }
}
