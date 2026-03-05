<?php

namespace App\Tests\Admin\Purchases;

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

class AdminPurchasesShowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->followRedirects();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var DatabaseToolCollection $dbTools */
        $dbTools = static::getContainer()->get(DatabaseToolCollection::class);

        $dbTools->get()->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);
    }

    private function loginAsAdminFromFixtures(): User
    {
        $admin = $this->em->getRepository(User::class)->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);
        self::assertNotNull($admin, 'Admin fixture introuvable.');

        $this->client->loginUser($admin);

        return $admin;
    }

    private function setPrivate(object $obj, string $prop, mixed $value): void
    {
        $ref = new \ReflectionClass($obj);
        if (!$ref->hasProperty($prop)) {
            throw new \RuntimeException(sprintf('Propriété "%s" introuvable sur %s', $prop, $ref->getName()));
        }

        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    }

    private function createUser(
        string $email,
        string $first,
        string $last,
        ?\DateTimeImmutable $archivedAt = null
    ): User {
        $u = (new User())
            ->setEmail($email)
            ->setFirstName($first)
            ->setLastName($last)
            ->setIsVerified(true)
            ->setStoredRoles([])
            ->setPassword('hash')
            ->setArchivedAt($archivedAt);

        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    private function createPurchase(
        User $user,
        string $status,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $paidAt,
        string $orderNumber
    ): Purchase {
        $p = new Purchase();
        $p->setUser($user);
        $p->setStatus($status);

        // champs privés sans setter
        $this->setPrivate($p, 'createdAt', $createdAt);
        $this->setPrivate($p, 'orderNumber', $orderNumber);

        if ($paidAt !== null) {
            $p->setPaidAt($paidAt);
        }

        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    private function getFixtureLessonGuitar1(): Lesson
    {
        $lesson = static::getContainer()->get('doctrine')->getManager()
            ->getRepository(Lesson::class)
            ->findOneBy(['title' => 'Découverte de l’instrument']);

        // fallback sûr: via référence si tu préfères
        if (!$lesson) {
            $lesson = $this->em->getReference(Lesson::class, $this->getReferenceId(ThemeFixtures::LESSON_GUITAR_1_REF));
        }

        self::assertInstanceOf(Lesson::class, $lesson);
        return $lesson;
    }

    private function getFixtureCursusGuitare(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy(['name' => 'Cursus d’initiation à la guitare']);
        if (!$cursus) {
            $cursus = $this->em->getReference(Cursus::class, $this->getReferenceId(ThemeFixtures::CURSUS_GUITARE_REF));
        }

        self::assertInstanceOf(Cursus::class, $cursus);
        return $cursus;
    }

    /**
     * Dans LiipFixtures, les références existent dans le "ReferenceRepository" interne,
     * mais en WebTestCase on ne l’a pas injecté directement.
     * Ici on prend l'ID via une requête simple et stable (grâce aux valeurs uniques de fixtures).
     */
    private function getReferenceId(string $refName): int
    {
        // On mappe nos refs fixtures sur des requêtes stables
        return match ($refName) {
            ThemeFixtures::LESSON_GUITAR_1_REF =>
                (int) $this->em->getRepository(Lesson::class)
                    ->findOneBy(['title' => 'Découverte de l’instrument'])
                    ?->getId(),

            ThemeFixtures::CURSUS_GUITARE_REF =>
                (int) $this->em->getRepository(Cursus::class)
                    ->findOneBy(['name' => 'Cursus d’initiation à la guitare'])
                    ?->getId(),

            default => throw new \InvalidArgumentException('Référence fixture non supportée dans le test: '.$refName),
        };
    }

    private function addLessonItem(Purchase $purchase, float $unitPrice, int $qty = 1): PurchaseItem
    {
        $lesson = $this->getFixtureLessonGuitar1();

        $it = new PurchaseItem();
        $it->setLesson($lesson);
        $it->setQuantity($qty);
        $it->setUnitPrice($unitPrice);

        $purchase->addItem($it);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->flush();

        return $it;
    }

    private function addCursusItem(Purchase $purchase, float $unitPrice, int $qty = 1): PurchaseItem
    {
        $cursus = $this->getFixtureCursusGuitare();

        $it = new PurchaseItem();
        $it->setCursus($cursus);
        $it->setQuantity($qty);
        $it->setUnitPrice($unitPrice);

        $purchase->addItem($it);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->flush();

        return $it;
    }

    public function testPurchaseIntrouvableRetourne404(): void
    {
        $this->loginAsAdminFromFixtures();

        $this->client->request('GET', '/admin/purchases/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPurchaseSansItemsAffichageOk(): void
    {
        $this->loginAsAdminFromFixtures();

        $u = $this->em->getRepository(User::class)->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);
        self::assertNotNull($u);

        $p = $this->createPurchase(
            $u,
            Purchase::STATUS_PENDING,
            new \DateTimeImmutable('2026-02-10 12:00:00'),
            null,
            'ORD-SHOW-NOITEMS'
        );

        $this->client->request('GET', '/admin/purchases/'.$p->getId());
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Commande ORD-SHOW-NOITEMS', $html);
        self::assertStringContainsString('Aucun article dans cette commande', $html);
    }

    public function testAfficheCommandeEtItemsLessonEtCursusEtSupporteItemVide(): void
    {
        $this->loginAsAdminFromFixtures();

        $u = $this->em->getRepository(User::class)->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);
        self::assertNotNull($u);

        $p = $this->createPurchase(
            $u,
            Purchase::STATUS_PAID,
            new \DateTimeImmutable('2026-02-20 10:00:00'),
            new \DateTimeImmutable('2026-02-20 10:05:00'),
            'ORD-SHOW-ITEMS'
        );

        // item lesson
        $this->addLessonItem($p, 10.00, 1);

        // item cursus
        $this->addCursusItem($p, 20.00, 2);

        // item vide (lesson=null & cursus=null) => doit afficher "—"
        $itEmpty = new PurchaseItem();
        $itEmpty->setQuantity(1);
        $itEmpty->setUnitPrice(5.00);
        $p->addItem($itEmpty);
        $p->calculateTotal();
        $this->em->persist($p);
        $this->em->flush();

        $this->client->request('GET', '/admin/purchases/'.$p->getId());
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('Commande ORD-SHOW-ITEMS', $html);
        self::assertStringContainsString('Détail des articles', $html);

        // typeLabel twig
        self::assertStringContainsString('Leçon', $html);
        self::assertStringContainsString('Cursus', $html);

        // item vide => "—" (type ou intitulé)
        self::assertStringContainsString('—', $html);

        // 3 items => "Article #"
        $countArticles = substr_count($html, 'Article #');
        self::assertTrue($countArticles >= 3, sprintf('Attendu >= 3 items (Article #), obtenu %d', $countArticles));
    }

    public function testCommandeDUnUserArchiveEstVisible(): void
    {
        $this->loginAsAdminFromFixtures();

        $archivedUser = $this->createUser(
            'archived_show@test.com',
            'Old',
            'Archived',
            new \DateTimeImmutable('2026-02-01 00:00:00')
        );

        $p = $this->createPurchase(
            $archivedUser,
            Purchase::STATUS_PAID,
            new \DateTimeImmutable('2026-02-02 10:00:00'),
            new \DateTimeImmutable('2026-02-02 10:05:00'),
            'ORD-ARCH-SHOW'
        );

        // Active archived_user si dispo : le controller show doit le désactiver
        $filters = $this->em->getFilters();
        try {
            if (!$filters->isEnabled('archived_user')) {
                $filters->enable('archived_user');
            }
        } catch (\Throwable $e) {
            self::markTestSkipped('Doctrine filter "archived_user" indisponible en env test.');
        }

        $this->client->request('GET', '/admin/purchases/'.$p->getId());
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Commande ORD-ARCH-SHOW', $html);
        self::assertStringContainsString('Archived Old', $html);
        self::assertStringContainsString('archived_show@test.com', $html);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em, $this->client);
        self::ensureKernelShutdown();
    }
}