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

        // Un seul createClient() pour éviter "kernel booted twice"
        $this->client = static::createClient();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();
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

        // Pas connecté => 0 bouton "Valider"
        self::assertSame(
            0,
            $crawler->filter('button.btn.btn-success:contains("Valider la leçon")')->count()
        );

        // 2 leçons => 2 liens "Ajouter au panier"
        self::assertSame(
            2,
            $crawler->filter('a.btn.btn-outline:contains("Ajouter au panier")')->count()
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
            ->setStatus('paid')
            ->setPaidAt(new \DateTimeImmutable());

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

        // 1 leçon achetée => 1 bouton "Valider"
        self::assertSame(
            1,
            $crawler->filter('button.btn.btn-success:contains("Valider la leçon")')->count()
        );

        // l’autre non achetée => 1 lien "Ajouter au panier"
        self::assertSame(
            1,
            $crawler->filter('a.btn.btn-outline:contains("Ajouter au panier")')->count()
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
            ->setStatus('paid')
            ->setPaidAt(new \DateTimeImmutable());

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

        // cursus acheté => accès aux 2 leçons => 2 boutons "Valider"
        self::assertSame(
            2,
            $crawler->filter('button.btn.btn-success:contains("Valider la leçon")')->count()
        );

        // 0 lien "Ajouter au panier"
        self::assertSame(
            0,
            $crawler->filter('a.btn.btn-outline:contains("Ajouter au panier")')->count()
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

        $this->client->request('GET', '/cart/add/cursus/' . $cursus->getId());

        self::assertResponseRedirects(); // vers app_login
    }
}