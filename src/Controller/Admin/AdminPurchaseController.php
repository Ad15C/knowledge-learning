<?php

namespace App\Controller\Admin;

use App\Entity\Purchase;
use App\Repository\PurchaseRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/purchases', name: 'admin_purchase_')]
class AdminPurchaseController extends AbstractController
{
    private function parseDateOnlyStrict(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        // Format strict YYYY-MM-DD (évite "2026-2-1", etc.)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return null;
        }

        // "!" => reset heure à 00:00:00
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (!$dt) {
            return null;
        }

        $errors = \DateTimeImmutable::getLastErrors();
        if (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
            return null;
        }

        return $dt;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        PurchaseRepository $repo,
        UserRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        // Permet d'afficher aussi les commandes d'utilisateurs archivés
        $filters = $em->getFilters();
        if ($filters->isEnabled('archived_user')) {
            $filters->disable('archived_user');
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

        // parsing strict : invalide => null (donc filtre ignoré)
        $dateFrom = $this->parseDateOnlyStrict($dateFromRaw);
        $dateTo   = $this->parseDateOnlyStrict($dateToRaw);

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

        // Liste des users pour le select
        $users = $userRepo->findActiveUsers();

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

            //  valeur HTML propre (si invalide => '')
            'dateFrom' => $dateFrom ? $dateFrom->format('Y-m-d') : '',
            'dateTo'   => $dateTo ? $dateTo->format('Y-m-d') : '',

            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'], priority: -10)]
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