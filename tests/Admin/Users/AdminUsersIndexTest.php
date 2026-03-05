<?php

namespace App\Tests\Admin\Users;

use App\DataFixtures\TestUserFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminUsersIndexTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        // IMPORTANT: suit le 301 http->https (et autres redirects)
        $this->client->followRedirects();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        // Recharge DB + fixtures de base (admin + user)
        /** @var DatabaseToolCollection $dbTools */
        $dbTools = static::getContainer()->get(DatabaseToolCollection::class);
        $dbTools->get()->loadFixtures([TestUserFixtures::class]);
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
        array $storedRoles = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $archivedAt = null
    ): User {
        $u = new User();
        $u->setEmail($email);
        $u->setFirstName($first);
        $u->setLastName($last);
        $u->setStoredRoles($storedRoles);
        $u->setPassword('hash');

        if ($createdAt) {
            $u->setCreatedAt($createdAt);
        }
        $u->setArchivedAt($archivedAt);

        $this->em->persist($u);

        return $u;
    }

    public function testIndexDefaults(): void
    {
        $this->loginAsAdminFromFixtures();

        $this->createUser('a@test.io', 'Alice', 'Alpha');
        $this->createUser('b@test.io', 'Bob', 'Beta');
        $this->createUser('c@test.io', 'Charly', 'Archived', [], null, new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();

        self::assertSame('active', $crawler->filter('input[name="status"]')->attr('value'));
        self::assertSame('1', $crawler->filter('input[name="page"]')->attr('value'));
        self::assertSame('name', $crawler->filter('#sort option[selected]')->attr('value'));
        self::assertSame('ASC', $crawler->filter('#dir option[selected]')->attr('value'));

        $html = (string) $this->client->getResponse()->getContent();

        // Par défaut: "active" => archived non affichés
        self::assertStringNotContainsString('Archived', $html);

        // Tri alphabétique sur (lastName, firstName) attendu dans la vue: "Alpha Alice" avant "Beta Bob"
        $posAlpha = mb_strpos($html, 'Alpha Alice');
        $posBeta  = mb_strpos($html, 'Beta Bob');
        self::assertNotFalse($posAlpha);
        self::assertNotFalse($posBeta);
        self::assertTrue($posAlpha < $posBeta);
    }

    public function testSearchMatchesFirstLastEmailCaseInsensitive(): void
    {
        $this->loginAsAdminFromFixtures();

        $this->createUser('john.smith@test.io', 'John', 'Smith');
        $this->createUser('marie.curie@test.io', 'Marie', 'Curie');
        $this->createUser('other@test.io', 'Other', 'Person');
        $this->em->flush();

        $this->client->request('GET', '/admin/users?q=joHN');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Smith John', $html);
        self::assertStringNotContainsString('Curie Marie', $html);

        $this->client->request('GET', '/admin/users?q=cuRIE');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Curie Marie', $html);
        self::assertStringNotContainsString('Smith John', $html);

        $this->client->request('GET', '/admin/users?q=SMITH@TEST.IO');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Smith John', $html);
        self::assertStringNotContainsString('Curie Marie', $html);
    }

    public function testStatusTabsActiveArchivedAll(): void
    {
        $this->loginAsAdminFromFixtures();

        $this->createUser('active1@test.io', 'Active', 'One', [], new \DateTimeImmutable('-3 days'), null);
        $this->createUser('arch1@test.io', 'Archived', 'One', [], new \DateTimeImmutable('-2 days'), new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        $this->client->request('GET', '/admin/users?status=active');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('One Active', $html);
        self::assertStringNotContainsString('One Archived', $html);

        $this->client->request('GET', '/admin/users?status=archived');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('One Active', $html);
        self::assertStringContainsString('One Archived', $html);

        $this->client->request('GET', '/admin/users?status=all');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('One Active', $html);
        self::assertStringContainsString('One Archived', $html);
    }

    public function testSortRecentOrdersByCreatedAtThenId(): void
    {
        $this->loginAsAdminFromFixtures();

        $this->createUser('old@test.io', 'Old', 'User', [], new \DateTimeImmutable('2020-01-01 10:00:00'));
        $same1 = $this->createUser('same1@test.io', 'Same', 'One', [], new \DateTimeImmutable('2021-01-01 10:00:00'));
        $same2 = $this->createUser('same2@test.io', 'Same', 'Two', [], new \DateTimeImmutable('2021-01-01 10:00:00'));
        $this->createUser('new@test.io', 'New', 'User', [], new \DateTimeImmutable('2022-01-01 10:00:00'));
        $this->em->flush();

        $this->client->request('GET', '/admin/users?sort=recent&dir=ASC&status=all');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();

        $posOld = mb_strpos($html, 'User Old');
        $posSame1 = mb_strpos($html, 'One Same');
        $posSame2 = mb_strpos($html, 'Two Same');
        $posNew = mb_strpos($html, 'User New');

        self::assertNotFalse($posOld);
        self::assertNotFalse($posSame1);
        self::assertNotFalse($posSame2);
        self::assertNotFalse($posNew);

        self::assertTrue($posOld < $posSame1);
        self::assertTrue($posSame1 < $posNew);

        // Tie-breaker "id" attendu quand createdAt identiques
        self::assertTrue($same1->getId() < $same2->getId());
        self::assertTrue($posSame1 < $posSame2);
    }

    public function testPaginationPage1OkAndPage999EmptyNoCrash(): void
    {
        $this->loginAsAdminFromFixtures();

        // Fixtures: 2 users (user + admin) déjà présents.
        // On ajoute 16 users actifs.
        for ($i = 1; $i <= 16; $i++) {
            $this->createUser("u{$i}@test.io", "First{$i}", "Last{$i}");
        }
        $this->em->flush();

        // On teste explicitement les actifs (comportement par défaut)
        $activeCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->andWhere('u.archivedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        // page 1 => min(15, total)
        $crawler = $this->client->request('GET', '/admin/users?page=1&status=active');
        self::assertResponseIsSuccessful();
        self::assertCount(min(15, $activeCount), $crawler->filter('table.admin-table tbody tr'));

        // page 2 => max(0, total - 15) mais capé à 15
        $expectedPage2 = max(0, $activeCount - 15);
        $expectedPage2 = min(15, $expectedPage2);

        $crawler = $this->client->request('GET', '/admin/users?page=2&status=active');
        self::assertResponseIsSuccessful();
        self::assertCount($expectedPage2, $crawler->filter('table.admin-table tbody tr'));

        // page 999 => vide mais sans crash
        $this->client->request('GET', '/admin/users?page=999&status=active');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Aucun utilisateur trouvé', (string) $this->client->getResponse()->getContent());
    }

    public function testPage0IsNormalizedTo1(): void
    {
        $this->loginAsAdminFromFixtures();

        $this->createUser('a@test.io', 'Alice', 'Alpha');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/users?page=0');
        self::assertResponseIsSuccessful();

        self::assertSame('1', $crawler->filter('input[name="page"]')->attr('value'));
        self::assertStringContainsString('Page 1/', $crawler->filter('.admin-page-subtitle')->text(''));
    }

    public function testInvalidActionFallsBackToEmpty(): void
    {
        $this->loginAsAdminFromFixtures();

        $this->createUser('a@test.io', 'Alice', 'Alpha');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/users?action=HACK&status=active');
        self::assertResponseIsSuccessful();

        self::assertSame('', $crawler->filter('input[name="action"]')->attr('value'));
    }

    public function testIndexHasFicheLinkAndItOpensUserShowPage(): void
    {
        $this->loginAsAdminFromFixtures();

        $u = $this->createUser('fiche@test.io', 'Fiche', 'User');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/users?status=active');
        self::assertResponseIsSuccessful();

        // Lien "Fiche" vers /admin/users/{id}
        $href = '/admin/users/'.$u->getId();
        self::assertGreaterThan(
            0,
            $crawler->filter('a.btn.btn-secondary[href="'.$href.'"]')->count(),
            'Le lien "Fiche" n’est pas présent ou ne pointe pas vers la route show.'
        );

        // Navigation vers la fiche
        $this->client->request('GET', $href);
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Fiche client', $html);
        self::assertStringContainsString('Fiche User', $html);
        self::assertStringContainsString('fiche@test.io', $html);
    }

    public function testShowDisplaysStatsAndEmptyProgressAndCertsWhenNoData(): void
    {
        $this->loginAsAdminFromFixtures();

        $u = $this->createUser('nodata@test.io', 'No', 'Data');
        $this->em->flush();

        $this->client->request('GET', '/admin/users/'.$u->getId());
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();

        // Header + sous-titre
        self::assertStringContainsString('Fiche client', $html);
        self::assertStringContainsString('No Data', $html);
        self::assertStringContainsString('nodata@test.io', $html);

        // Stats (le contrôleur passe toujours "stats")
        self::assertStringContainsString('Leçons achetées', $html);
        self::assertStringContainsString('Leçons validées', $html);
        self::assertStringContainsString('Certifications', $html);

        // États vides du partial _progress_and_certs.html.twig
        self::assertStringContainsString('— Aucune leçon validée.', $html);
        self::assertStringContainsString('— Aucune leçon en cours.', $html);
        self::assertStringContainsString('— Aucune certification.', $html);
    }
}