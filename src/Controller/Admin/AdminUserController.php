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

        // Action pour afficher un message flash après une opération (edit, delete, restore)
        $action = (string) $request->query->get('action', '');
        if (!in_array($action, ['', 'edit', 'delete', 'restore'], true)) {
            $action = '';
        }

        if ($includeArchived) {
            $filters = $em->getFilters();
            if ($filters->isEnabled('archived_user')) {
                $filters->disable('archived_user');
            }
        }

        // Sécurité : valider les paramètres de tri
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
            'action' => $action,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $repo
    ): Response {
        $current = $this->getUser();
        $isSelf = $current instanceof User && $current->getId() === $user->getId();

        // Rôles réellement stockés en base (sans ROLE_USER automatique)
        $originalRoles = $user->getStoredRoles();
        $wasAdmin = in_array('ROLE_ADMIN', $originalRoles, true);

        $form = $this->createForm(UserType::class, $user, [
            'allow_roles_edit' => !$isSelf, // pas de champ rôle sur soi-même
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // 1) Sécurité : on ne peut pas modifier ses propres rôles
            if ($isSelf) {
                $user->setStoredRoles($originalRoles);
            }

            // 2) Sécurité : éviter de se retrouver sans admin
            $isAdminNow = in_array('ROLE_ADMIN', $user->getStoredRoles(), true);

            if ($wasAdmin && !$isAdminNow) {
                $adminsCount = $repo->countActiveAdmins();

                // adminsCount inclut encore cet admin tant qu'on n'a pas flush
                if ($adminsCount <= 1) {
                    $user->setStoredRoles(['ROLE_ADMIN']);
                    $this->addFlash('danger', "Action refusée : il doit rester au moins un administrateur actif.");
                    return $this->redirectToRoute('admin_users_edit', ['id' => $user->getId()]);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('admin_users_index', ['action' => 'edit']);
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'isSelf' => $isSelf,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $repo
    ): Response {
        if (!$this->isCsrfTokenValid('archive_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Empêcher un admin d'archiver son propre compte
        $current = $this->getUser();
        if ($current instanceof User && $current->getId() === $user->getId()) {
            $this->addFlash('danger', 'Tu ne peux pas archiver ton propre compte.');
            return $this->redirectToRoute('admin_users_index');
        }

        // Déjà archivé ?
        if ($user->isArchived()) {
            $this->addFlash('info', 'Utilisateur déjà archivé.');
            return $this->redirectToRoute('admin_users_index');
        }

        // Empêcher d'archiver le dernier admin actif
        if (in_array('ROLE_ADMIN', $user->getStoredRoles(), true)) {
            if ($repo->countActiveAdmins() <= 1) {
                $this->addFlash('danger', "Action refusée : il doit rester au moins un administrateur actif.");
                return $this->redirectToRoute('admin_users_index');
            }
        }

        $user->setArchivedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Utilisateur archivé.');
        return $this->redirectToRoute('admin_users_index', ['action' => 'delete']);
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
        return $this->redirectToRoute('admin_users_index', ['action' => 'restore']);
    }
}