<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RgpdController extends AbstractController
{
    #[Route('/politique-confidentialite', name: 'privacy_policy')]
    public function privacyPolicy(): Response
    {
        return $this->render('rgpd/privacy_policy.html.twig');
    }

    #[Route('/mentions-legales', name: 'legal_notice')]
    public function legal(): Response
    {
        return $this->render('rgpd/legal_notice.html.twig');
    }

    #[Route('/cookies', name: 'cookies_policy')]
    public function cookies(): Response
    {
        return $this->render('rgpd/cookies_policy.html.twig');
    }

    #[Route('/conditions-generales-de-vente', name: 'cgv_policy')]
      public function conditionsGenerales(): Response
    {
        return $this->render('rgpd/cgv_policy.html.twig');
    }
}