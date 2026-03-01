<?php

namespace App\Controller\Admin;

use App\Entity\Purchase;
use App\Repository\PurchaseRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/purchases', name: 'admin_purchase_')]
class AdminPurchaseController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PurchaseRepository $repo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        // Vu qu'il y a le filtre doctrine "archived_user" comme dans AdminUserController :
        $filters = $em->getFilters();
        if ($filters->isEnabled('archived_user')) {
            $filters->disable('archived_user'); // on veut pouvoir voir les commandes d’archivés
        }

        $q = trim((string) $request->query->get('q', ''));

        // status: all|cart|paid|pending|canceled|
        $status = (string) $request->query->get('status', 'all');
        $allowedStatus = array_merge(['all'], Purchase::STATUSES);

        if (!in_array($status, $allowedStatus, true)) {
            $status = 'all';
        }

        $userId = (int) $request->query->get('user', 0);
        $userId = $userId > 0 ? $userId : null;

        $dateFromRaw = (string) $request->query->get('dateFrom', '');
        $dateToRaw   = (string) $request->query->get('dateTo', '');

        $dateFrom = null;
        if ($dateFromRaw !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dateFromRaw);
            $dateFrom = $dt ?: null;
        }

        $dateTo = null;
        if ($dateToRaw !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dateToRaw);
            $dateTo = $dt ?: null;
        }

        $sort = (string) $request->query->get('sort', 'createdAt'); // createdAt|status|total|paidAt|user
        if (!in_array($sort, ['createdAt', 'status', 'total', 'paidAt', 'user'], true)) {
            $sort = 'createdAt';
        }

        $dir = strtoupper((string) $request->query->get('dir', 'DESC')); // ASC|DESC
        $dir = $dir === 'ASC' ? 'ASC' : 'DESC';

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;

        $result = $repo->findForAdminListPaginated(
            $q,
            $status === 'all' ? null : $status,
            $userId,
            $dateFrom,
            $dateTo,
            $sort,
            $dir,
            $page,
            $perPage
        );

        // Optionnel : alimenter un select user (si tu veux)
        $users = $userRepo->findActiveUsers(); // tu as déjà cette méthode

        return $this->render('admin/purchases/index.html.twig', [
            'purchases' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'pages' => (int) ceil($result['total'] / $perPage),

            'q' => $q,
            'status' => $status,
            'allowedStatus' => $allowedStatus,

            'userId' => $userId,
            'users' => $users,

            'dateFrom' => $dateFromRaw,
            'dateTo' => $dateToRaw,

            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, PurchaseRepository $repo, EntityManagerInterface $em): Response
    {
        $filters = $em->getFilters();
        if ($filters->isEnabled('archived_user')) {
            $filters->disable('archived_user');
        }

        $purchase = $repo->findOneForAdminShow($id);
        if (!$purchase) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $this->render('admin/purchases/show.html.twig', [
            'purchase' => $purchase,
            'items' => $purchase->getItems(),
        ]);
    }
}