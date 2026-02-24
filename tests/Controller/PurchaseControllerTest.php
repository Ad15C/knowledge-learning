<?php

namespace App\Tests\Controller;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Repository\PurchaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PurchaseControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private PurchaseRepository $purchaseRepo;

    protected function setUp(): void
    {
        // IMPORTANT: createClient() DOIT être le premier boot du kernel
        $this->client = static::createClient();

        $container = static::getContainer();

        // charge fixtures (DB reset + data)
        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->purchaseRepo = $container->get(PurchaseRepository::class);
    }

    private function getTestUser(): \App\Entity\User
    {
        $user = $this->em->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user);

        return $user;
    }

    private function getOneLesson(): \App\Entity\Lesson
    {
        $lesson = $this->em->getRepository(\App\Entity\Lesson::class)
            ->findOneBy(['title' => 'Découverte de l’instrument']);

        self::assertNotNull($lesson);

        return $lesson;
    }

    private function getOneCursus(): \App\Entity\Cursus
    {
        $cursus = $this->em->getRepository(\App\Entity\Cursus::class)
            ->findOneBy(['name' => 'Cursus d’initiation à la guitare']);

        self::assertNotNull($cursus);

        return $cursus;
    }

    public function testCartShowRedirectsWhenAnonymous(): void
    {
        $this->client->request('GET', '/cart');
        self::assertResponseRedirects(); // vers login
    }

    public function testCartShowEmptyWhenLoggedInAndNoCart(): void
    {
        $this->client->loginUser($this->getTestUser());

        $this->client->request('GET', '/cart');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon Panier');
        self::assertSelectorExists('.cart-empty');
    }

    public function testAddLessonCreatesCartAndRendersInTwig(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $this->client->loginUser($user);

        $this->client->request('GET', '/cart/add/lesson/'.$lesson->getId());

        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon Panier');
        self::assertSelectorTextContains('.cart-item-card h3', $lesson->getTitle());
        self::assertSelectorTextContains('.cart-type', 'Leçon');
        self::assertSelectorExists('.cart-total');
        self::assertSelectorExists('a.btn-remove');
        self::assertSelectorExists('a[href="/cart/pay"]');
    }

    public function testAddSameLessonTwiceDoesNotDuplicate(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $this->client->loginUser($user);

        $this->client->request('GET', '/cart/add/lesson/'.$lesson->getId());
        $this->client->followRedirect();

        $this->client->request('GET', '/cart/add/lesson/'.$lesson->getId());
        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();

        $purchase = $this->purchaseRepo->findOneBy(['user' => $user, 'status' => 'cart']);
        self::assertNotNull($purchase);
        self::assertCount(1, $purchase->getItems());
    }

    public function testAddCursusRendersAsCursusInTwig(): void
    {
        $user = $this->getTestUser();
        $cursus = $this->getOneCursus();

        $this->client->loginUser($user);

        $this->client->request('GET', '/cart/add/cursus/'.$cursus->getId());

        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.cart-item-card h3', $cursus->getName());
        self::assertSelectorTextContains('.cart-type', 'Cursus');
    }

    public function testRemoveLessonFromCart(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $this->client->loginUser($user);

        // crée un panier + item en DB
        $purchase = $this->purchaseRepo->findOneBy(['user' => $user, 'status' => 'cart']);
        if (!$purchase) {
            $purchase = (new Purchase())->setUser($user)->setStatus('cart');
            $this->em->persist($purchase);
        }

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setLesson($lesson)
            ->setUnitPrice($lesson->getPrice())
            ->setQuantity(1);

        $this->em->persist($item);
        $purchase->addItem($item);
        $purchase->calculateTotal();
        $this->em->flush();

        $this->client->request('GET', '/cart/remove/lesson/'.$lesson->getId());

        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();

        $purchase = $this->purchaseRepo->findOneBy(['user' => $user, 'status' => 'cart']);
        self::assertNotNull($purchase);
        self::assertCount(0, $purchase->getItems());
        self::assertSelectorExists('.cart-empty');
    }

    public function testPayWithEmptyCartRedirectsAndShowsEmpty(): void
    {
        $this->client->loginUser($this->getTestUser());

        $this->client->request('GET', '/cart/pay');

        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();

        self::assertSelectorExists('.cart-empty');
    }

    public function testPayFlowRedirectsToSuccessAndShowsLinksToLessons(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $this->client->loginUser($user);

        $this->client->request('GET', '/cart/add/lesson/'.$lesson->getId());
        $this->client->followRedirect();

        $this->client->request('GET', '/cart/pay');

        self::assertResponseRedirects(); // /cart/success/{orderNumber}
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Commande réussie');
        self::assertSelectorExists('.order-number strong');
        self::assertSelectorExists('.order-date');
        self::assertSelectorTextContains('.lesson-badge', 'Leçon');
        self::assertSelectorExists('a.cart-link'); // lien "Accéder"

        $paid = $this->purchaseRepo->findOneBy(['user' => $user, 'status' => 'paid']);
        self::assertNotNull($paid);
        self::assertNotNull($paid->getPaidAt());
    }

    public function testSuccessWithWrongOrderNumberReturns404(): void
    {
        $this->client->loginUser($this->getTestUser());

        $this->client->request('GET', '/cart/success/ORD-19000101-deadbeef');

        self::assertResponseStatusCodeSame(404);
    }
}