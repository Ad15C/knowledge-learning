<?php

namespace App\Tests\Controller;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\User;
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
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $container = static::getContainer();

        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->purchaseRepo = $container->get(PurchaseRepository::class);
    }

    private function getTestUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);
        self::assertNotNull($user);

        return $user;
    }

    private function getOneLesson(): Lesson
    {
        $lesson = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Découverte de l’instrument']);
        self::assertNotNull($lesson);

        return $lesson;
    }

    private function getOneCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy(['name' => 'Cursus d’initiation à la guitare']);
        self::assertNotNull($cursus);

        return $cursus;
    }

    private function extractCsrfToken(\Symfony\Component\DomCrawler\Crawler $crawler, string $formSelector): string
    {
        $form = $crawler->filter($formSelector);
        self::assertGreaterThan(
            0,
            $form->count(),
            sprintf('Form not found with selector: %s', $formSelector)
        );

        $tokenInput = $form->filter('input[name="_token"]');
        self::assertGreaterThan(0, $tokenInput->count(), 'CSRF input _token not found in form.');

        $token = (string) $tokenInput->attr('value');
        self::assertNotSame('', $token, 'CSRF token value is empty.');

        return $token;
    }

    public function testCartShowRedirectsWhenAnonymous(): void
    {
        $this->client->request('GET', '/cart');
        self::assertResponseRedirects();
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

        $cursusId = $lesson->getCursus()->getId();
        $crawler = $this->client->request('GET', '/cursus/' . $cursusId);
        self::assertResponseIsSuccessful();

        $formSelector = sprintf('form[action="/cart/add/lesson/%d"]', $lesson->getId());
        $token = $this->extractCsrfToken($crawler, $formSelector);

        $this->client->request('POST', '/cart/add/lesson/' . $lesson->getId(), [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon Panier');
        self::assertSelectorTextContains('.cart-item-card h3', $lesson->getTitle());
        self::assertSelectorTextContains('.cart-type', 'Leçon');
        self::assertSelectorExists('.cart-total');
        self::assertSelectorExists('form[action="/cart/pay"] button[type="submit"]');
    }

    public function testAddSameLessonTwiceDoesNotDuplicate(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $this->client->loginUser($user);

        $cursusId = $lesson->getCursus()->getId();

        $crawler = $this->client->request('GET', '/cursus/' . $cursusId);
        self::assertResponseIsSuccessful();
        $formSelector = sprintf('form[action="/cart/add/lesson/%d"]', $lesson->getId());
        $token = $this->extractCsrfToken($crawler, $formSelector);

        $this->client->request('POST', '/cart/add/lesson/' . $lesson->getId(), ['_token' => $token]);
        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/cursus/' . $cursusId);
        self::assertResponseIsSuccessful();
        $token2 = $this->extractCsrfToken($crawler, $formSelector);

        $this->client->request('POST', '/cart/add/lesson/' . $lesson->getId(), ['_token' => $token2]);
        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();

        $purchase = $this->purchaseRepo->findOneBy(['user' => $user, 'status' => Purchase::STATUS_CART]);
        self::assertNotNull($purchase);
        self::assertCount(1, $purchase->getItems());
    }

    public function testAddCursusRendersAsCursusInTwig(): void
    {
        $user = $this->getTestUser();
        $cursus = $this->getOneCursus();

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/themes/' . $cursus->getTheme()->getId());
        self::assertResponseIsSuccessful();

        $formSelector = sprintf('form[action="/cart/add/cursus/%d"]', $cursus->getId());
        $token = $this->extractCsrfToken($crawler, $formSelector);

        $this->client->request('POST', '/cart/add/cursus/' . $cursus->getId(), [
            '_token' => $token,
        ]);

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

        $cursusId = $lesson->getCursus()->getId();
        $crawler = $this->client->request('GET', '/cursus/' . $cursusId);
        self::assertResponseIsSuccessful();

        $addFormSelector = sprintf('form[action="/cart/add/lesson/%d"]', $lesson->getId());
        $addToken = $this->extractCsrfToken($crawler, $addFormSelector);

        $this->client->request('POST', '/cart/add/lesson/' . $lesson->getId(), ['_token' => $addToken]);
        self::assertResponseRedirects('/cart');
        $crawlerCart = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $removeFormSelector = sprintf('form[action="/cart/remove/lesson/%d"]', $lesson->getId());
        $removeToken = $this->extractCsrfToken($crawlerCart, $removeFormSelector);

        $this->client->request('POST', '/cart/remove/lesson/' . $lesson->getId(), [
            '_token' => $removeToken,
        ]);

        self::assertResponseRedirects('/cart');
        $this->client->followRedirect();

        self::assertSelectorExists('.cart-empty');
    }

    public function testPayWithEmptyCartRedirectsAndShowsEmpty(): void
    {
        $this->client->loginUser($this->getTestUser());

        $this->client->request('GET', '/cart');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.cart-empty');
        self::assertSelectorNotExists('form[action="/cart/pay"]');
    }

    public function testPayFlowRedirectsToSuccessAndShowsLinksToLessons(): void
    {
        $user = $this->getTestUser();
        $lesson = $this->getOneLesson();

        $this->client->loginUser($user);

        $cursusId = $lesson->getCursus()->getId();
        $crawler = $this->client->request('GET', '/cursus/' . $cursusId);
        self::assertResponseIsSuccessful();

        $addFormSelector = sprintf('form[action="/cart/add/lesson/%d"]', $lesson->getId());
        $addToken = $this->extractCsrfToken($crawler, $addFormSelector);

        $this->client->request('POST', '/cart/add/lesson/' . $lesson->getId(), ['_token' => $addToken]);
        self::assertResponseRedirects('/cart');
        $crawlerCart = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $payToken = $this->extractCsrfToken($crawlerCart, 'form[action="/cart/pay"]');

        $this->client->request('POST', '/cart/pay', [
            '_token' => $payToken,
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Commande réussie');
        self::assertSelectorExists('.order-number strong');
        self::assertSelectorExists('.order-date');
        self::assertSelectorExists('a.cart-success-link, a.cart-link');

        $paid = $this->purchaseRepo->findOneBy(['user' => $user, 'status' => Purchase::STATUS_PAID]);
        self::assertNotNull($paid);
        self::assertNotNull($paid->getPaidAt());
        self::assertNotEmpty($paid->getOrderNumber());
    }

    public function testSuccessWithWrongOrderNumberReturns404(): void
    {
        $this->client->loginUser($this->getTestUser());

        $this->client->request('GET', '/cart/success/ORD-19000101-deadbeef');
        self::assertResponseStatusCodeSame(404);
    }
}