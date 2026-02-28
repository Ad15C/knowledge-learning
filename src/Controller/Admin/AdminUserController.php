<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\UserType;
use App\Repository\CertificationRepository;
use App\Repository\LessonValidatedRepository;
use App\Repository\PurchaseItemRepository;
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

        $sort = (string) $request->query->get('sort', 'name'); // name|recent
        if (!in_array($sort, ['name', 'recent'], true)) {
            $sort = 'name';
        }

        $dir = strtoupper((string) $request->query->get('dir', 'ASC')); // ASC|DESC
        $dir = $dir === 'DESC' ? 'DESC' : 'ASC';

        // Mode d'affichage (boutons)
        $action = (string) $request->query->get('action', '');
        if (!in_array($action, ['', 'edit', 'delete', 'restore'], true)) {
            $action = '';
        }

        // Onglets statut
        $status = (string) $request->query->get('status', 'active'); // active|archived|all
        if (!in_array($status, ['active', 'archived', 'all'], true)) {
            $status = 'active';
        }

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 15;

        // On inclut les archivés si status != active
        $includeArchived = $status !== 'active';

        // Si tu as un Doctrine Filter qui cache les archivés, on le désactive quand on veut les voir
        if ($includeArchived) {
            $filters = $em->getFilters();
            if ($filters->isEnabled('archived_user')) {
                $filters->disable('archived_user');
            }
        }

        $result = $repo->findForAdminListPaginated($q, $status, $sort, $dir, $page, $perPage);

        return $this->render('admin/users/index.html.twig', [
            'users' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'pages' => (int) ceil($result['total'] / $perPage),

            'q' => $q,
            'sort' => $sort,
            'dir' => $dir,
            'status' => $status,
            'includeArchived' => $includeArchived,
            'action' => $action,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(
        int $id,
        EntityManagerInterface $em,
        UserRepository $repo,
        LessonValidatedRepository $lvRepo,
        CertificationRepository $certRepo,
        PurchaseItemRepository $purchaseItemRepo
    ): Response {
        $user = $this->findUserIncludingArchived($id, $em, $repo);

        $validatedLessons = $lvRepo->findValidatedLessonsForUser($user);
        $certifications = $certRepo->findByUserWithTargets($user);
        $purchasedLessons = $purchaseItemRepo->findLessonsPurchasedByUser($user);

        $validatedIds = array_values(array_filter(array_map(
            static fn($lv) => $lv->getLesson()?->getId(),
            $validatedLessons
        )));

        $inProgressLessons = array_values(array_filter(
            $purchasedLessons,
            static fn($lesson) => $lesson && !in_array($lesson->getId(), $validatedIds, true)
        ));

        $stats = [
            'purchasedCount' => count($purchasedLessons),
            'validatedCount' => count($validatedLessons),
            'certificationsCount' => count($certifications),
        ];

        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
            'validatedLessons' => $validatedLessons,
            'inProgressLessons' => $inProgressLessons,
            'certifications' => $certifications,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $repo
    ): Response {
        $user = $this->findUserIncludingArchived($id, $em, $repo);

        $current = $this->getUser();
        $isSelf = $current instanceof User && $current->getId() === $user->getId();

        // Rôles réellement stockés en base
        $originalRoles = $user->getStoredRoles();
        $wasAdmin = in_array('ROLE_ADMIN', $originalRoles, true);

        $form = $this->createForm(UserType::class, $user, [
            'allow_roles_edit' => !$isSelf,
        ]);

        $form->handleRequest($request);

        // Récupère le status courant pour revenir au bon onglet après save
        $status = (string) $request->query->get('status', 'active');
        if (!in_array($status, ['active', 'archived', 'all'], true)) {
            $status = 'active';
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // 1) Sécurité : on ne peut pas modifier ses propres rôles
            if ($isSelf) {
                $user->setStoredRoles($originalRoles);
            }

            // 2) Sécurité : éviter de se retrouver sans admin
            $isAdminNow = in_array('ROLE_ADMIN', $user->getStoredRoles(), true);

            if ($wasAdmin && !$isAdminNow) {
                $adminsCount = $repo->countActiveAdmins();
                if ($adminsCount <= 1) {
                    $user->setStoredRoles(['ROLE_ADMIN']);
                    $this->addFlash('danger', "Action refusée : il doit rester au moins un administrateur actif.");

                    return $this->redirectToRoute('admin_users_edit', [
                        'id' => $user->getId(),
                        'status' => $status,
                    ]);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('admin_users_index', [
                'action' => 'edit',
                'status' => $status,
            ]);
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'isSelf' => $isSelf,
            'status' => $status,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $repo
    ): Response {
        $user = $this->findUserIncludingArchived($id, $em, $repo);

        if (!$this->isCsrfTokenValid('archive_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Empêcher un admin d'archiver son propre compte
        $current = $this->getUser();
        if ($current instanceof User && $current->getId() === $user->getId()) {
            $this->addFlash('danger', 'Tu ne peux pas archiver ton propre compte.');
            return $this->redirectToRoute('admin_users_index', ['action' => 'delete', 'status' => 'active']);
        }

        // Déjà archivé ?
        if ($user->isArchived()) {
            $this->addFlash('info', 'Utilisateur déjà archivé.');
            return $this->redirectToRoute('admin_users_index', ['action' => 'delete', 'status' => 'archived']);
        }

        // Empêcher d'archiver le dernier admin actif
        if (in_array('ROLE_ADMIN', $user->getStoredRoles(), true)) {
            if ($repo->countActiveAdmins() <= 1) {
                $this->addFlash('danger', "Action refusée : il doit rester au moins un administrateur actif.");
                return $this->redirectToRoute('admin_users_index', ['action' => 'delete', 'status' => 'active']);
            }
        }

        $user->setArchivedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Utilisateur archivé.');

        return $this->redirectToRoute('admin_users_index', [
            'action' => 'delete',
            'status' => 'archived',
        ]);
    }

    #[Route('/{id}/restore', name: 'restore', methods: ['POST'])]
    public function restore(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $repo
    ): Response {
        $user = $this->findUserIncludingArchived($id, $em, $repo);

        if (!$this->isCsrfTokenValid('restore_user_'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$user->isArchived()) {
            $this->addFlash('info', 'Utilisateur déjà actif.');
            return $this->redirectToRoute('admin_users_index', ['action' => 'delete', 'status' => 'active']);
        }

        $user->setArchivedAt(null);
        $em->flush();

        $this->addFlash('success', 'Utilisateur restauré.');

        return $this->redirectToRoute('admin_users_index', [
            'action' => 'delete',
            'status' => 'active',
        ]);
    }

    private function findUserIncludingArchived(
        int $id,
        EntityManagerInterface $em,
        UserRepository $repo
    ): User {
        $filters = $em->getFilters();
        if ($filters->isEnabled('archived_user')) {
            $filters->disable('archived_user');
        }

        $user = $repo->find($id);
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        return $user;
    }
}