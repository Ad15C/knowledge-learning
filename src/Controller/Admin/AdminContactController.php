<?php

namespace App\Controller\Admin;

use App\Repository\ContactRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/contact', name: 'admin_contact_')]
class AdminContactController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ContactRepository $repo): Response
    {
        $messages = $repo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('admin/contact/index.html.twig', [
            'messages' => $messages,
            'filter' => 'all',
        ]);
    }

    #[Route('/unhandled', name: 'unhandled', methods: ['GET'])]
    public function unhandled(ContactRepository $repo): Response
    {
        $messages = $repo->findBy(['handled' => false], ['createdAt' => 'DESC']);

        return $this->render('admin/contact/index.html.twig', [
            'messages' => $messages,
            'filter' => 'unhandled',
        ]);
    }

    #[Route('/handled', name: 'handled', methods: ['GET'])]
    public function handled(ContactRepository $repo): Response
    {
        $messages = $repo->findBy(['handled' => true], ['createdAt' => 'DESC']);

        return $this->render('admin/contact/index.html.twig', [
            'messages' => $messages,
            'filter' => 'handled',
        ]);
    }
}