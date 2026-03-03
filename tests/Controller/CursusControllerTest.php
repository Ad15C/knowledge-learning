<?php

namespace App\Tests\Controller;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CursusControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();
    }

    private function forceOrderNumber(Purchase $purchase): void
    {
        $ref = new \ReflectionClass(Purchase::class);
        $propOrderNumber = $ref->getProperty('orderNumber');
        $propOrderNumber->setAccessible(true);
        $propOrderNumber->setValue(
            $purchase,
            'ORD-TEST-' . date('YmdHis') . '-' . bin2hex(random_bytes(4))
        );
    }

    public function test_show_not_logged_in_only_add_to_cart(): void
    {
        $fixtures = $this->databaseTool->loadFixtures([
            ThemeFixtures::class,
        ]);

        $cursus = $fixtures->getReferenceRepository()
            ->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);

        $crawler = $this->client->request('GET', '/cursus/' . $cursus->getId());
        self::assertResponseIsSuccessful();

        // Pas connecté => 0 bouton "Accéder & valider"
        self::assertSame(
            0,
            $crawler->filter('a.btn.btn-success:contains("Accéder")')->count()
        );

        // 2 leçons => 2 boutons "Ajouter au panier" (dans ton twig c'est un <button>)
        self::assertSame(
            2,
            $crawler->filter('button.btn.btn-outline:contains("Ajouter au panier")')->count()
        );

        self::assertSelectorTextContains('h1', $cursus->getName());
        self::assertCount(2, $crawler->filter('.lesson-card'));
    }

    public function test_show_logged_in_buys_one_lesson(): void
    {
        $fixtures = $this->databaseTool->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        $cursus = $fixtures->getReferenceRepository()
            ->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);

        $lesson1 = $fixtures->getReferenceRepository()
            ->getReference(ThemeFixtures::LESSON_GUITAR_1_REF, Lesson::class);

        $user = $fixtures->getReferenceRepository()
            ->getReference(TestUserFixtures::USER_REF, User::class);

        // Achat paid de 1 leçon
        $purchase = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable());

        $this->forceOrderNumber($purchase);

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setLesson($lesson1)
            ->setQuantity(1)
            ->setUnitPrice($lesson1->getPrice() ?? 26);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->persist($item);
        $this->em->flush();

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/cursus/' . $cursus->getId());
        self::assertResponseIsSuccessful();

        // Dans ton twig: si accès => lien "Accéder & valider" (pas un button)
        self::assertSame(
            1,
            $crawler->filter('a.btn.btn-success:contains("Accéder")')->count()
        );

        // l’autre non achetée => 1 bouton "Ajouter au panier"
        self::assertSame(
            1,
            $crawler->filter('button.btn.btn-outline:contains("Ajouter au panier")')->count()
        );
    }

    public function test_show_logged_in_buys_cursus(): void
    {
        $fixtures = $this->databaseTool->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        $cursus = $fixtures->getReferenceRepository()
            ->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);

        $user = $fixtures->getReferenceRepository()
            ->getReference(TestUserFixtures::USER_REF, User::class);

        // Achat paid du cursus
        $purchase = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable());

        $this->forceOrderNumber($purchase);

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setCursus($cursus)
            ->setQuantity(1)
            ->setUnitPrice($cursus->getPrice() ?? 50);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->persist($item);
        $this->em->flush();

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/cursus/' . $cursus->getId());
        self::assertResponseIsSuccessful();

        // cursus acheté => accès aux 2 leçons => 2 liens "Accéder & valider"
        self::assertSame(
            2,
            $crawler->filter('a.btn.btn-success:contains("Accéder")')->count()
        );

        // 0 bouton "Ajouter au panier"
        self::assertSame(
            0,
            $crawler->filter('button.btn.btn-outline:contains("Ajouter au panier")')->count()
        );
    }

    public function test_show_404_if_not_found(): void
    {
        $this->client->request('GET', '/cursus/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function test_add_cursus_to_cart_redirects_when_not_logged_in(): void
    {
        $fixtures = $this->databaseTool->loadFixtures([ThemeFixtures::class]);

        $cursus = $fixtures->getReferenceRepository()
            ->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);

        // IMPORTANT: route en POST (pas GET)
        $this->client->request('POST', '/cart/add/cursus/' . $cursus->getId(), [
            '_token' => 'dummy', // pas besoin d'un vrai token: l'accès est refusé avant
        ]);

        self::assertResponseRedirects(); // vers app_login
    }
}