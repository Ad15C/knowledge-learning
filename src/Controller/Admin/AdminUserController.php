<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users', name: 'admin_users_')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, UserRepository $repo, EntityManagerInterface $em): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'name');   // name|recent
        $dir  = (string) $request->query->get('dir', 'ASC');     // ASC|DESC
        $includeArchived = (bool) $request->query->get('archived', false);

        // Désactive le filtre global uniquement si on veut voir les archivés en admin
        if ($includeArchived) {
            $filters = $em->getFilters();
            if ($filters->isEnabled('archived_user')) {
                $filters->disable('archived_user');
            }
        }

        // Whitelists
        if (!in_array($sort, ['name', 'recent'], true)) {
            $sort = 'name';
        }
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';

        $users = $repo->findForAdminList($q, $sort, $dir, $includeArchived);

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'includeArchived' => $includeArchived,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(UserType::class, $user, [
            'is_admin' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('archive_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Empêcher un admin d'archiver son propre compte
        if ($this->getUser() instanceof User && $this->getUser()->getId() === $user->getId()) {
            $this->addFlash('danger', 'Tu ne peux pas archiver ton propre compte.');
            return $this->redirectToRoute('admin_users_index');
        }

        // Empêcher de ré-archiver un user déjà archivé
        if ($user->isArchived()) {
            $this->addFlash('info', 'Utilisateur déjà archivé.');
            return $this->redirectToRoute('admin_users_index');
        }

        // Soft delete
        $user->setArchivedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Utilisateur archivé.');
        return $this->redirectToRoute('admin_users_index');
    }
    

    #[Route('/{id}/restore', name: 'restore', methods: ['POST'])]
    public function restore(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('restore_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$user->isArchived()) {
            $this->addFlash('info', 'Utilisateur déjà actif.');
            return $this->redirectToRoute('admin_users_index');
        }

        $user->setArchivedAt(null);
        $em->flush();

        $this->addFlash('success', 'Utilisateur restauré.');
        return $this->redirectToRoute('admin_users_index');
    }
}