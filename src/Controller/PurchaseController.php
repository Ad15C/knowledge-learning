<?php

namespace App\Controller;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Repository\PurchaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PurchaseController extends AbstractController
{
    private EntityManagerInterface $em;
    private PurchaseRepository $purchaseRepo;

    public function __construct(EntityManagerInterface $em, PurchaseRepository $purchaseRepo)
    {
        $this->em = $em;
        $this->purchaseRepo = $purchaseRepo;
    }

        /**
     * Afficher le panier
     */
    #[Route('/cart', name: 'cart_show')]
    public function show(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour voir votre panier.');
            return $this->redirectToRoute('app_login');
        }

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => 'cart'
        ]);

        return $this->render('cart/show.html.twig', [
            'purchase' => $purchase
        ]);
    }

    /**
     * Ajouter un cursus au panier
     */
    #[Route('/cart/add/cursus/{id}', name: 'cart_add_cursus')]
    public function addCursus(Cursus $cursus): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour ajouter au panier.');
            return $this->redirectToRoute('app_login');
        }

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => 'cart'
        ]);

        if (!$purchase) {
            $purchase = new Purchase();
            $purchase->setUser($user);
            $purchase->setStatus('cart');
            $purchase->setCreatedAt(new \DateTime());
            $this->em->persist($purchase);
        }

        // Vérifier si déjà présent
        foreach ($purchase->getItems() as $item) {
            if ($item->getCursus() && $item->getCursus()->getId() === $cursus->getId()) {
                $this->addFlash('info', 'Ce cursus est déjà dans votre panier.');
                return $this->redirectToRoute('themes_index');
            }
        }

        $item = new PurchaseItem();
        $item->setPurchase($purchase);
        $item->setCursus($cursus);
        $item->setPrice($cursus->getPrice());
        $this->em->persist($item);
        $this->em->flush();

        $this->addFlash('success', 'Cursus ajouté au panier.');
        return $this->redirectToRoute('themes_index');
    }

    /**
     * Ajouter une leçon au panier
     */
    #[Route('/cart/add/lesson/{id}', name: 'cart_add_lesson')]
    public function addLesson(Lesson $lesson): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour ajouter au panier.');
            return $this->redirectToRoute('app_login');
        }

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => 'cart'
        ]);

        if (!$purchase) {
            $purchase = new Purchase();
            $purchase->setUser($user);
            $purchase->setStatus('cart');
            $purchase->setCreatedAt(new \DateTime());
            $this->em->persist($purchase);
        }

        // Vérifier si déjà présent
        foreach ($purchase->getItems() as $item) {
            if ($item->getLesson() && $item->getLesson()->getId() === $lesson->getId()) {
                $this->addFlash('info', 'Cette leçon est déjà dans votre panier.');
                return $this->redirectToRoute('themes_index');
            }
        }

        $item = new PurchaseItem();
        $item->setPurchase($purchase);
        $item->setLesson($lesson);
        $item->setPrice($lesson->getPrice());
        $this->em->persist($item);
        $this->em->flush();

        $this->addFlash('success', 'Leçon ajoutée au panier.');
        return $this->redirectToRoute('themes_index');
    }

    /**
     * Supprimer un item du panier
     */
    #[Route('/cart/remove/{type}/{id}', name: 'cart_remove')]
    public function remove(string $type, int $id): RedirectResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Action non autorisée.');
            return $this->redirectToRoute('cart_show');
        }

        $repo = $this->em->getRepository(PurchaseItem::class);
        if ($type === 'cursus') {
            $item = $repo->findOneBy(['cursus' => $id, 'purchase' => $this->purchaseRepo->findOneBy(['user' => $user, 'status' => 'cart'])]);
        } elseif ($type === 'lesson') {
            $item = $repo->findOneBy(['lesson' => $id, 'purchase' => $this->purchaseRepo->findOneBy(['user' => $user, 'status' => 'cart'])]);
        } else {
            $this->addFlash('error', 'Type d’élément inconnu.');
            return $this->redirectToRoute('cart_show');
        }

        if (!$item) {
            $this->addFlash('info', 'Élément introuvable dans votre panier.');
            return $this->redirectToRoute('cart_show');
        }

        $this->em->remove($item);
        $this->em->flush();

        $this->addFlash('success', 'Élément supprimé du panier.');
        return $this->redirectToRoute('cart_show');
    }

    /**
     * Confirmation de paiement
     */
    #[Route('/cart/success', name: 'cart_success')]
    public function success(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour voir cette page.');
            return $this->redirectToRoute('app_login');
        }

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => 'paid'
        ]);

        return $this->render('cart/success.html.twig', [
            'purchase' => $purchase
        ]);
    }
}
