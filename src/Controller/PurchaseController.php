<?php

namespace App\Controller;

use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\Lesson;
use App\Entity\Cursus;
use App\Repository\PurchaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PurchaseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PurchaseRepository $purchaseRepo
    ) {}

    #[Route('/cart', name: 'cart_show', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->getUser();

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => Purchase::STATUS_CART,
        ]);

        return $this->render('cart/show.html.twig', [
            'purchase' => $purchase
        ]);
    }

    #[Route('/cart/add/lesson/{id}', name: 'cart_add_lesson', methods: ['POST'])]
    public function addLesson(Request $request, Lesson $lesson): Response
    {
        if (!$this->isCsrfTokenValid('cart_add_lesson_'.$lesson->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => Purchase::STATUS_CART,
        ]);

        if (!$purchase) {
            $purchase = new Purchase();
            $purchase->setUser($user);
            $this->em->persist($purchase);
        }

        foreach ($purchase->getItems() as $existingItem) {
            if ($existingItem->getLesson() === $lesson) {
                $this->addFlash('info', 'Cette leçon est déjà dans le panier.');
                return $this->redirectToRoute('cart_show');
            }
        }

        $item = new PurchaseItem();
        $item->setPurchase($purchase);
        $item->setLesson($lesson);
        $item->setUnitPrice((float) $lesson->getPrice());

        $this->em->persist($item);
        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->flush();

        $this->addFlash('success', 'Leçon ajoutée au panier.');
        return $this->redirectToRoute('cart_show');
    }

    #[Route('/cart/add/cursus/{id}', name: 'cart_add_cursus', methods: ['POST'])]
    public function addCursus(Request $request, Cursus $cursus): Response
    {
        if (!$this->isCsrfTokenValid('cart_add_cursus_'.$cursus->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => Purchase::STATUS_CART,
        ]);

        if (!$purchase) {
            $purchase = new Purchase();
            $purchase->setUser($user);
            $this->em->persist($purchase);
        }

        foreach ($purchase->getItems() as $existingItem) {
            if ($existingItem->getCursus() === $cursus) {
                $this->addFlash('info', 'Ce cursus est déjà dans le panier.');
                return $this->redirectToRoute('cart_show');
            }
        }

        $item = new PurchaseItem();
        $item->setPurchase($purchase);
        $item->setCursus($cursus);
        $item->setUnitPrice((float) $cursus->getPrice());

        $this->em->persist($item);
        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->flush();

        $this->addFlash('success', 'Cursus ajouté au panier.');
        return $this->redirectToRoute('cart_show');
    }

    #[Route('/cart/remove/{type}/{id}', name: 'cart_remove', methods: ['POST'])]
    public function remove(Request $request, string $type, int $id): Response
    {
        if (!in_array($type, ['lesson', 'cursus'], true)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('cart_remove_'.$type.'_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => Purchase::STATUS_CART,
        ]);

        if (!$purchase) {
            return $this->redirectToRoute('cart_show');
        }

        foreach ($purchase->getItems() as $item) {
            if ($type === 'lesson' && $item->getLesson()?->getId() === $id) {
                $purchase->removeItem($item);
                $this->em->remove($item);
                break;
            }

            if ($type === 'cursus' && $item->getCursus()?->getId() === $id) {
                $purchase->removeItem($item);
                $this->em->remove($item);
                break;
            }
        }

        $purchase->calculateTotal();
        $this->em->flush();

        $this->addFlash('success', 'Item supprimé.');
        return $this->redirectToRoute('cart_show');
    }

    #[Route('/cart/pay', name: 'cart_pay', methods: ['POST'])]
    public function pay(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('cart_pay', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => Purchase::STATUS_CART,
        ]);

        if (!$purchase || $purchase->getItems()->isEmpty()) {
            $this->addFlash('error', 'Panier vide.');
            return $this->redirectToRoute('cart_show');
        }

        // Simulation : tu peux mettre pending ici si tu veux, puis paid ensuite via webhook
        // $purchase->markPending();
        $purchase->calculateTotal();
        $purchase->markPaid();

        $this->em->flush();

        return $this->redirectToRoute('cart_success', [
            'orderNumber' => $purchase->getOrderNumber()
        ]);
    }

    #[Route('/cart/success/{orderNumber}', name: 'cart_success', methods: ['GET'])]
    public function success(string $orderNumber): Response
    {
        $user = $this->getUser();

        $purchase = $this->purchaseRepo->findOneBy([
            'orderNumber' => $orderNumber,
            'user' => $user,
            'status' => Purchase::STATUS_PAID,
        ]);

        if (!$purchase) {
            throw $this->createNotFoundException();
        }

        return $this->render('cart/success.html.twig', [
            'purchase' => $purchase
        ]);
    }
}