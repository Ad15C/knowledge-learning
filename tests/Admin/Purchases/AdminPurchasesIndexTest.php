<?php

namespace App\Tests\Admin\Purchases;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminPurchasesIndexTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $db;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->followRedirects();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->db = $this->em->getConnection();

        /** @var DatabaseToolCollection $dbTools */
        $dbTools = static::getContainer()->get(DatabaseToolCollection::class);

        // Recharge DB test + fixtures de base
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

    /**
     * Création robuste : on persiste, puis on force createdAt + orderNumber en base (SQLite)
     * pour éviter les surprises Doctrine/SQLite quand on veut tester des bornes de date.
     */
    private function createPurchase(
        User $user,
        string $status,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $paidAt,
        float $total,
        string $orderNumber
    ): Purchase {
        $p = new Purchase();
        $p->setUser($user);
        $p->setStatus($status);

        // total via item + calculateTotal()
        $item = new PurchaseItem();
        $item->setQuantity(1);
        $item->setUnitPrice($total);
        $p->addItem($item);
        $p->calculateTotal();

        if ($paidAt !== null) {
            $p->setPaidAt($paidAt);
        }

        $this->em->persist($p);
        $this->em->flush();

        // force en DB
        $this->db->executeStatement(
            'UPDATE purchase SET created_at = :createdAt, order_number = :orderNumber WHERE id = :id',
            [
                'createdAt'   => $createdAt->format('Y-m-d H:i:s'),
                'orderNumber' => $orderNumber,
                'id'          => $p->getId(),
            ]
        );

        $this->em->refresh($p);

        return $p;
    }

    private function assertOrderAppearsInThisOrder(string $html, array $needlesInOrder): void
    {
        $pos = -1;
        foreach ($needlesInOrder as $needle) {
            $p = mb_strpos($html, $needle);
            self::assertNotFalse($p, sprintf('Chaîne "%s" introuvable dans la page.', $needle));
            self::assertTrue($p > $pos, sprintf('Ordre incorrect: "%s" apparaît trop tôt.', $needle));
            $pos = $p;
        }
    }

    public function testAccesPage1SansFiltre(): void
    {
        $this->loginAsAdminFromFixtures();

        $this->client->request('GET', '/admin/purchases');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Commandes', $html);
    }

    public function testPaginationPage1DernierePageEtPageTropGrandeListeVideSansCrash(): void
    {
        $this->loginAsAdminFromFixtures();

        $u = $this->em->getRepository(User::class)->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);
        self::assertNotNull($u);

        // 45 => 3 pages si perPage=20
        for ($i = 1; $i <= 45; $i++) {
            $this->createPurchase(
                $u,
                Purchase::STATUS_PAID,
                new \DateTimeImmutable('2026-01-01 10:00:00'),
                new \DateTimeImmutable('2026-01-01 10:05:00'),
                (float) $i,
                sprintf('ORD-PAG-%03d', $i)
            );
        }

        $this->client->request('GET', '/admin/purchases?page=1');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Aucune commande trouvée', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/purchases?page=3');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Aucune commande trouvée', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/purchases?page=999');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aucune commande trouvée', (string) $this->client->getResponse()->getContent());
    }

    public function testFiltreQMatchOrderNumberEmailPrenomNomLowercase(): void
    {
        $this->loginAsAdminFromFixtures();

        $u = $this->createUser('alpha@example.com', 'Jean', 'Dupont');
        $this->createPurchase(
            $u,
            Purchase::STATUS_PENDING,
            new \DateTimeImmutable('2026-02-01 09:00:00'),
            null,
            99.99,
            'ORD-SEARCH-XYZ'
        );

        $this->client->request('GET', '/admin/purchases?q=ord-search-xyz');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('ORD-SEARCH-XYZ', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/purchases?q=alpha@example.com');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('ORD-SEARCH-XYZ', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/purchases?q=jean');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('ORD-SEARCH-XYZ', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/admin/purchases?q=dupont');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('ORD-SEARCH-XYZ', (string) $this->client->getResponse()->getContent());
    }

    public function testFiltreStatusValeurAutoriseeEtValeurInterditeFallbackAll(): void
    {
        $this->loginAsAdminFromFixtures();

        $u = $this->createUser('buyer2@test.com', 'Alice', 'Martin');
        $this->createPurchase($u, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-01-10 10:00:00'), new \DateTimeImmutable('2026-01-10 10:10:00'), 10, 'ORD-ST-PAID');
        $this->createPurchase($u, Purchase::STATUS_CART, new \DateTimeImmutable('2026-01-11 10:00:00'), null, 20, 'ORD-ST-CART');

        $this->client->request('GET', '/admin/purchases?status=paid');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('ORD-ST-PAID', $html);
        self::assertStringNotContainsString('ORD-ST-CART', $html);

        // valeur interdite => doit afficher all
        $this->client->request('GET', '/admin/purchases?status=hack');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('ORD-ST-PAID', $html);
        self::assertStringContainsString('ORD-ST-CART', $html);
    }

    public function testFiltreUserLimiteAUnUser(): void
    {
        $this->loginAsAdminFromFixtures();

        $u1 = $this->createUser('u1@test.com', 'Bob', 'Alpha');
        $u2 = $this->createUser('u2@test.com', 'Carl', 'Beta');

        $this->createPurchase($u1, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-01-01 10:00:00'), new \DateTimeImmutable('2026-01-01 10:05:00'), 15, 'ORD-U1');
        $this->createPurchase($u2, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-01-01 11:00:00'), new \DateTimeImmutable('2026-01-01 11:05:00'), 25, 'ORD-U2');

        $this->client->request('GET', '/admin/purchases?user=' . $u1->getId());
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('ORD-U1', $html);
        self::assertStringNotContainsString('ORD-U2', $html);
    }

    public function testFiltreDatesDateFromSeulDateToSeulInclutFinJourneeFromGreaterThanToInvalideIgnoree(): void
    {
        $this->loginAsAdminFromFixtures();

        $u = $this->createUser('dates@test.com', 'Dora', 'Dates');

        $this->createPurchase($u, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-02-10 12:00:00'), new \DateTimeImmutable('2026-02-10 12:30:00'), 10, 'ORD-D-10');
        $this->createPurchase($u, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-02-20 00:00:01'), new \DateTimeImmutable('2026-02-20 00:10:00'), 20, 'ORD-D-20-A');
        $this->createPurchase($u, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-02-20 23:59:59'), new \DateTimeImmutable('2026-02-21 00:05:00'), 30, 'ORD-D-20-B');

        // dateFrom seul => doit garder 20-A et 20-B
        $this->client->request('GET', '/admin/purchases?dateFrom=2026-02-20');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('ORD-D-10', $html);
        self::assertStringContainsString('ORD-D-20-A', $html);
        self::assertStringContainsString('ORD-D-20-B', $html);

        // dateTo seul => doit inclure fin de journée
        $this->client->request('GET', '/admin/purchases?dateTo=2026-02-20');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('ORD-D-10', $html);
        self::assertStringContainsString('ORD-D-20-A', $html);
        self::assertStringContainsString('ORD-D-20-B', $html);

        // from > to => liste vide
        $this->client->request('GET', '/admin/purchases?dateFrom=2026-02-21&dateTo=2026-02-20');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aucune commande trouvée', (string) $this->client->getResponse()->getContent());

        // date invalide => ignorée => tout ressort
        $this->client->request('GET', '/admin/purchases?dateFrom=2026-99-99');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('ORD-D-10', $html);
        self::assertStringContainsString('ORD-D-20-A', $html);
        self::assertStringContainsString('ORD-D-20-B', $html);
    }

    public function testTriTotalAscDescPaidAtNullsEtUserSurLastName(): void
    {
        $this->loginAsAdminFromFixtures();

        $uA = $this->createUser('a@test.com', 'A', 'Alpha');
        $uZ = $this->createUser('z@test.com', 'Z', 'Zulu');

        $this->createPurchase($uA, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-01-01 10:00:00'), new \DateTimeImmutable('2026-01-01 10:10:00'), 10, 'ORD-T-10');
        $this->createPurchase($uA, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-01-01 11:00:00'), new \DateTimeImmutable('2026-01-01 11:10:00'), 30, 'ORD-T-30');
        $this->createPurchase($uA, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-01-01 12:00:00'), new \DateTimeImmutable('2026-01-01 12:10:00'), 20, 'ORD-T-20');

        $this->createPurchase($uZ, Purchase::STATUS_PENDING, new \DateTimeImmutable('2026-01-02 10:00:00'), null, 5, 'ORD-PNULL');
        $this->createPurchase($uZ, Purchase::STATUS_PAID, new \DateTimeImmutable('2026-01-02 11:00:00'), new \DateTimeImmutable('2026-01-03 08:00:00'), 6, 'ORD-PSET');

        $this->client->request('GET', '/admin/purchases?sort=total&dir=ASC');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        $this->assertOrderAppearsInThisOrder($html, ['ORD-PNULL', 'ORD-PSET', 'ORD-T-10']);

        $this->client->request('GET', '/admin/purchases?sort=total&dir=DESC');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        $this->assertOrderAppearsInThisOrder($html, ['ORD-T-30', 'ORD-T-10']);

        $this->client->request('GET', '/admin/purchases?sort=paidAt&dir=ASC');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        $this->assertOrderAppearsInThisOrder($html, ['ORD-PNULL', 'ORD-PSET']);

        $this->client->request('GET', '/admin/purchases?sort=user&dir=ASC');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        $this->assertOrderAppearsInThisOrder($html, ['Alpha', 'Zulu']);
    }

    public function testCommandesUtilisateursArchivesApparaissentBienFiltreArchivedUserDesactiveDansIndex(): void
    {
        $this->loginAsAdminFromFixtures();

        $archivedUser = $this->createUser(
            'archived@test.com',
            'Old',
            'Archived',
            new \DateTimeImmutable('2025-01-01 00:00:00')
        );

        $this->createPurchase(
            $archivedUser,
            Purchase::STATUS_PAID,
            new \DateTimeImmutable('2026-01-05 10:00:00'),
            new \DateTimeImmutable('2026-01-05 10:05:00'),
            12,
            'ORD-ARCH-1'
        );

        // Active le filtre si dispo (le controller doit le désactiver pour l’index admin)
        $filters = $this->em->getFilters();
        try {
            if (!$filters->isEnabled('archived_user')) {
                $filters->enable('archived_user');
            }
        } catch (\Throwable $e) {
            self::markTestSkipped('Doctrine filter "archived_user" indisponible en env test.');
        }

        $this->client->request('GET', '/admin/purchases');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('ORD-ARCH-1', $html);
    }
}