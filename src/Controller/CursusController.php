<?php

namespace App\Controller;

use App\Entity\Cursus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CursusController extends AbstractController
{
    #[Route('/cursus/{id}', name: 'cursus_show')]
    public function show(Cursus $cursus): Response
    {
        return $this->render('cursus/show.html.twig', [
            'cursus' => $cursus
        ]);
    }
}
