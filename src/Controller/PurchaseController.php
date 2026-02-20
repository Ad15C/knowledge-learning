<?php

namespace App\Controller;

use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\Lesson;
use App\Entity\Cursus;
use App\Repository\PurchaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PurchaseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private PurchaseRepository $purchaseRepo
    ) {}

    #[Route('/cart', name: 'cart_show')]
    public function show(): Response
    {
        $user = $this->getUser();

        if (!$user) {
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

    #[Route('/cart/add/lesson/{id}', name: 'cart_add_lesson')]
    public function addLesson(Lesson $lesson): Response
    {
        $user = $this->getUser();

        if (!$user) {
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
            $this->em->persist($purchase);
        }

        // éviter doublons
        foreach ($purchase->getItems() as $existingItem) {
            if ($existingItem->getLesson() === $lesson) {
                $this->addFlash('info', 'Cette leçon est déjà dans le panier.');
                return $this->redirectToRoute('cart_show');
            }
        }

        $item = new PurchaseItem();
        $item->setPurchase($purchase);
        $item->setLesson($lesson);
        $item->setUnitPrice($lesson->getPrice());

        $this->em->persist($item);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->flush();

        $this->addFlash('success', 'Leçon ajoutée au panier.');

        return $this->redirectToRoute('cart_show');
    }

    #[Route('/cart/add/cursus/{id}', name: 'cart_add_cursus')]
    public function addCursus(Cursus $cursus): Response
    {
        $user = $this->getUser();

        if (!$user) {
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
        $item->setUnitPrice($cursus->getPrice());

        $this->em->persist($item);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->flush();

        $this->addFlash('success', 'Cursus ajouté au panier.');

        return $this->redirectToRoute('cart_show');
    }

    #[Route('/cart/remove/{type}/{id}', name: 'cart_remove')]
    public function remove(string $type, int $id): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => 'cart'
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

    /* Simulation Stripe */
    #[Route('/cart/pay', name: 'cart_pay')]
    public function pay(): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $purchase = $this->purchaseRepo->findOneBy([
            'user' => $user,
            'status' => 'cart'
        ]);

        if (!$purchase || $purchase->getItems()->isEmpty()) {

            $this->addFlash('error', 'Panier vide.');

            return $this->redirectToRoute('cart_show');
        }

        // simulation paiement Stripe
        $purchase->calculateTotal();
        $purchase->setStatus('paid');
        $purchase->setPaidAt(new \DateTimeImmutable());

        $this->em->flush();

        return $this->redirectToRoute('cart_success', [
            'orderNumber' => $purchase->getOrderNumber()
        ]);
    }

    #[Route('/cart/success/{orderNumber}', name: 'cart_success')]
    public function success(string $orderNumber): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $purchase = $this->purchaseRepo->findOneBy([
            'orderNumber' => $orderNumber,
            'user' => $user,
            'status' => 'paid'
        ]);

        if (!$purchase) {
            throw $this->createNotFoundException();
        }

        return $this->render('cart/success.html.twig', [
            'purchase' => $purchase
        ]);
    }
}