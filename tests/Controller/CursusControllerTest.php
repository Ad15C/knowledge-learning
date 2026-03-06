<?php

namespace App\Tests\Controller;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
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

    private function createPaidPurchase(User $user): Purchase
    {
        $purchase = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable());

        $this->forceOrderNumber($purchase);

        return $purchase;
    }

    private function createPaidLessonPurchase(User $user, Lesson $lesson): void
    {
        $purchase = $this->createPaidPurchase($user);

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setLesson($lesson)
            ->setQuantity(1)
            ->setUnitPrice((float) $lesson->getPrice());

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->persist($item);
        $this->em->flush();
        $this->em->clear();
    }

    private function createPaidCursusPurchase(User $user, Cursus $cursus): void
    {
        $purchase = $this->createPaidPurchase($user);

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setCursus($cursus)
            ->setQuantity(1)
            ->setUnitPrice((float) $cursus->getPrice());

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->persist($item);
        $this->em->flush();
        $this->em->clear();
    }

    private function markLessonCompleted(User $user, Lesson $lesson): void
    {
        $validated = (new LessonValidated())
            ->setUser($user)
            ->setLesson($lesson)
            ->markCompleted();

        $this->em->persist($validated);
        $this->em->flush();
        $this->em->clear();
    }

    public function testShowNotLoggedInShowsLoginToBuyButtons(): void
    {
        $fixtures = $this->databaseTool->loadFixtures([
            ThemeFixtures::class,
        ]);

        $cursus = $fixtures->getReferenceRepository()
            ->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);

        $crawler = $this->client->request('GET', '/cursus/' . $cursus->getId());
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1', $cursus->getName());
        self::assertCount(2, $crawler->filter('.lesson-card'));

        self::assertSame(
            0,
            $crawler->filter('a.btn.btn-success')->count(),
            'A visitor should not see access buttons.'
        );

        self::assertSame(
            2,
            $crawler->filter('a.btn.btn-primary:contains("Se connecter pour acheter")')->count()
        );

        self::assertSame(
            0,
            $crawler->filter('button.btn.btn-outline:contains("Ajouter au panier")')->count()
        );
    }

    public function testShowLoggedInWithOneLessonPurchaseShowsOneAccessAndOneCartButton(): void
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

        $this->createPaidLessonPurchase($user, $lesson1);

        $user = $this->em->getRepository(User::class)->find($user->getId());
        $cursus = $this->em->getRepository(Cursus::class)->find($cursus->getId());

        self::assertNotNull($user);
        self::assertNotNull($cursus);

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/cursus/' . $cursus->getId());
        self::assertResponseIsSuccessful();

        self::assertSame(
            1,
            $crawler->filter('a.btn.btn-success:contains("Accéder à la leçon")')->count()
        );

        self::assertSame(
            1,
            $crawler->filter('button.btn.btn-outline:contains("Ajouter au panier")')->count()
        );

        self::assertSame(
            0,
            $crawler->filter('a.btn.btn-primary:contains("Se connecter pour acheter")')->count()
        );
    }

    public function testShowLoggedInWithWholeCursusPurchaseShowsAllLessonsAccessible(): void
    {
        $fixtures = $this->databaseTool->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        $cursus = $fixtures->getReferenceRepository()
            ->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);

        $user = $fixtures->getReferenceRepository()
            ->getReference(TestUserFixtures::USER_REF, User::class);

        $this->createPaidCursusPurchase($user, $cursus);

        $user = $this->em->getRepository(User::class)->find($user->getId());
        $cursus = $this->em->getRepository(Cursus::class)->find($cursus->getId());

        self::assertNotNull($user);
        self::assertNotNull($cursus);

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/cursus/' . $cursus->getId());
        self::assertResponseIsSuccessful();

        self::assertSame(
            2,
            $crawler->filter('a.btn.btn-success:contains("Accéder à la leçon")')->count()
        );

        self::assertSame(
            0,
            $crawler->filter('button.btn.btn-outline:contains("Ajouter au panier")')->count()
        );

        self::assertSame(
            0,
            $crawler->filter('a.btn.btn-primary:contains("Se connecter pour acheter")')->count()
        );
    }

    public function testShowLoggedInWithCompletedLessonShowsRevoirLaLecon(): void
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

        $this->createPaidLessonPurchase($user, $lesson1);

        $user = $this->em->getRepository(User::class)->find($user->getId());
        $lesson1 = $this->em->getRepository(Lesson::class)->find($lesson1->getId());

        self::assertNotNull($user);
        self::assertNotNull($lesson1);

        $this->markLessonCompleted($user, $lesson1);

        $user = $this->em->getRepository(User::class)->find($user->getId());
        $cursus = $this->em->getRepository(Cursus::class)->find($cursus->getId());

        self::assertNotNull($user);
        self::assertNotNull($cursus);

        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/cursus/' . $cursus->getId());
        self::assertResponseIsSuccessful();

        self::assertSame(
            1,
            $crawler->filter('a.btn.btn-success:contains("Revoir la leçon")')->count()
        );

        self::assertSame(
            1,
            $crawler->filter('.badge.badge-success:contains("Validée")')->count()
        );
    }

    public function testShowReturns404IfNotFound(): void
    {
        $this->client->request('GET', '/cursus/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAddCursusToCartRedirectsWhenNotLoggedIn(): void
    {
        $fixtures = $this->databaseTool->loadFixtures([
            ThemeFixtures::class,
        ]);

        $cursus = $fixtures->getReferenceRepository()
            ->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);

        $this->client->request('POST', '/cart/add/cursus/' . $cursus->getId(), [
            '_token' => 'dummy',
        ]);

        self::assertResponseRedirects();
    }
}