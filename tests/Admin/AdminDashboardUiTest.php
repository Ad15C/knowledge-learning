<?php

namespace App\Tests\Admin;

use App\DataFixtures\TestUserFixtures;
use App\Entity\User;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AdminDashboardUiTest extends WebTestCase
{
    private function createClientAndLoginAdmin(): KernelBrowser
    {
        self::ensureKernelShutdown();

        $client = self::createClient([], [
            'HTTPS' => 'on',
        ]);

        $container = static::getContainer();

        /** @var DatabaseToolCollection $dbTools */
        $dbTools = $container->get(DatabaseToolCollection::class);
        $executor = $dbTools->get()->loadFixtures([TestUserFixtures::class]);
        $refRepo = $executor->getReferenceRepository();

        /** @var User $admin */
        $admin = $refRepo->getReference(TestUserFixtures::ADMIN_REF, User::class);

        $client->loginUser($admin);

        return $client;
    }

    private function requestFollowRedirects(
        KernelBrowser $client,
        string $method,
        string $url,
        int $max = 7
    ): Crawler {
        $crawler = $client->request($method, $url);

        while ($client->getResponse()->isRedirect() && $max-- > 0) {
            $crawler = $client->followRedirect();
        }

        return $crawler;
    }

    private function assertContainsAny(string $haystack, array $needles, string $message): void
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return;
            }
        }

        $this->fail($message . ' (aucune variante trouvée: ' . implode(' | ', $needles) . ')');
    }

    public function test_admin_dashboard_page_renders_and_contains_expected_menu_and_cards(): void
    {
        $client = $this->createClientAndLoginAdmin();

        $crawler = $this->requestFollowRedirects($client, 'GET', '/admin');

        $status = $client->getResponse()->getStatusCode();
        $pathFinal = $client->getRequest()->getPathInfo();

        $this->assertStringNotContainsString(
            '/login',
            $pathFinal,
            sprintf('Un admin ne doit pas finir sur /login. Final path=%s status=%d', $pathFinal, $status)
        );

        $this->assertSame(
            200,
            $status,
            sprintf('Le dashboard admin doit finir en 200. Final path=%s status=%d', $pathFinal, $status)
        );

        $html = $client->getResponse()->getContent() ?? '';

        // Header admin
        $this->assertStringContainsString('Accueil', $html);
        $this->assertStringContainsString('Dashboard Admin', $html);

        $this->assertContainsAny(
            $html,
            ["Vue d&#039;ensemble", "Vue d’ensemble", "Vue d'ensemble"],
            'Le menu admin doit contenir "Vue d’ensemble".'
        );

        $this->assertStringContainsString('Clients', $html);
        $this->assertStringContainsString('Thèmes', $html);
        $this->assertStringContainsString('Cursus', $html);
        $this->assertStringContainsString('Leçons', $html);
        $this->assertStringContainsString('Commandes', $html);
        $this->assertStringContainsString('Messages contact', $html);

        // Sidebar admin
        $this->assertStringContainsString('Administration', $html);
        $this->assertContainsAny(
            $html,
            ["Vue d’ensemble", "Vue d&#039;ensemble", "Vue d'ensemble"],
            'La sidebar admin doit contenir "Vue d’ensemble".'
        );
        $this->assertStringContainsString('Utilisateurs', $html);
        $this->assertStringContainsString('Thèmes pédagogiques', $html);
        $this->assertStringContainsString('Commandes & paiements', $html);
        $this->assertStringContainsString('Messagerie', $html);

        // Cards dashboard
        $this->assertStringContainsString('Utilisateurs', $html);
        $this->assertStringContainsString('Thèmes pédagogiques', $html);
        $this->assertStringContainsString('Parcours (Cursus)', $html);
        $this->assertStringContainsString('Leçons', $html);
        $this->assertStringContainsString('Commandes & paiements', $html);
        $this->assertStringContainsString('Messagerie', $html);

        // Vérifs liens clés
        $this->assertGreaterThan(0, $crawler->filter('a[href*="/admin"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href*="/admin/users"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href*="/admin/themes"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href*="/admin/cursus"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href*="/admin/lesson"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href*="/admin/purchases"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('a[href*="/admin/contact"]')->count());
    }
}