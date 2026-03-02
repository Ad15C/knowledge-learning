<?php

namespace App\Controller\Admin;

use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/contact', name: 'admin_contact_')]
class AdminContactController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, ContactRepository $repo): Response
    {
        // Filtres (query params)
        $filters = [
            'subject' => $request->query->get('subject'),
            'status'  => $request->query->get('status'),
            'q'       => trim((string) $request->query->get('q', '')),
        ];

        // Filtre "client particulier" par email (facultatif)
        $email = trim((string) $request->query->get('email', ''));
        if ($email !== '') {
            $filters['q'] = trim($filters['q'] . ' ' . $email);
        }

        $contacts = $repo->findByFilters($filters);

        // Labels sujets (pour select)
        $subjects = [
            'theme' => 'Question sur un thème',
            'cursus' => 'Question sur un cursus',
            'lesson' => 'Question sur une leçon',
            'payment' => 'Question sur le paiement',
            'validation' => 'Question sur la validation du cours',
            'certification' => 'Question sur la certification',
            'registration' => 'Question sur l’inscription',
            'login' => 'Question sur la connexion',
            'other' => 'Autre question',
        ];

        return $this->render('admin/contact/index.html.twig', [
            'contacts' => $contacts,
            'filters' => $filters,
            'subjects' => $subjects,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, ContactRepository $repo, EntityManagerInterface $em): Response
    {
        $contact = $repo->find($id);
        if (!$contact) {
            throw $this->createNotFoundException();
        }

        // Option : auto "lu" quand on ouvre
        if (!$contact->isRead()) {
            $contact->markRead();
            $em->flush();
        }

        return $this->render('admin/contact/show.html.twig', [
            'contact' => $contact,
        ]);
    }

    #[Route('/{id}/read', name: 'mark_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(int $id, Request $request, ContactRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('contact_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $contact = $repo->find($id) ?? throw $this->createNotFoundException();

        $contact->markRead();
        $em->flush();

        // retour à la page précédente si possible
        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_contact_index'));
    }

    #[Route('/{id}/unread', name: 'mark_unread', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markUnread(int $id, Request $request, ContactRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('contact_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $contact = $repo->find($id) ?? throw $this->createNotFoundException();

        $contact->markUnread();
        $em->flush();

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_contact_index'));
    }

    #[Route('/{id}/handled', name: 'mark_handled', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markHandled(int $id, Request $request, ContactRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('contact_'.$id, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $contact = $repo->find($id) ?? throw $this->createNotFoundException();

        $contact->setHandled(true);
        $em->flush();

        return $this->redirect($request->headers->get('referer') ?? $this->generateUrl('admin_contact_index'));
    }
}