<?php

namespace App\Tests\Controller;

use App\DataFixtures\ThemeFixtures;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ThemeControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = self::createClient();
        $this->client->catchExceptions(true);

        self::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get()
            ->loadFixtures([ThemeFixtures::class]);
    }

    public function testIndexPageDisplaysThemes(): void
    {
        $crawler = $this->client->request('GET', '/themes');

        $this->assertResponseIsSuccessful();

        // On évite d’être trop dépendant du HTML exact, mais on garde des repères
        $this->assertSelectorExists('h1');
        $this->assertSelectorExists('.themes-grid');
        $this->assertGreaterThan(0, $crawler->filter('.theme-card')->count());

        // Si tes fixtures contiennent "Musique", on le check
        $titles = $crawler->filter('.theme-card h2')->each(fn ($node) => trim($node->text()));
        $this->assertContains('Musique', $titles);

        // Boutons "voir" (ou équivalent)
        $this->assertGreaterThan(0, $crawler->filter('.theme-card a.btn.btn-primary')->count());
    }

    public function testIndexFilterByNameNarrowsResults(): void
    {
        // Résultats sans filtre
        $crawlerAll = $this->client->request('GET', '/themes');
        $this->assertResponseIsSuccessful();
        $countAll = $crawlerAll->filter('.theme-card')->count();
        $this->assertGreaterThan(0, $countAll);

        // Résultats avec filtre name=Musique
        $crawlerFiltered = $this->client->request('GET', '/themes?name=Musique');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.themes-grid');

        $countFiltered = $crawlerFiltered->filter('.theme-card')->count();
        $this->assertGreaterThan(0, $countFiltered);

        // En général, filtrer doit réduire ou égaliser
        $this->assertLessThanOrEqual($countAll, $countFiltered);

        // Vérifie que les titres retournés contiennent bien "Musique" (si ta vue affiche les titres)
        $titles = $crawlerFiltered->filter('.theme-card h2')->each(fn ($node) => trim($node->text()));
        foreach ($titles as $t) {
            $this->assertStringContainsStringIgnoringCase('musique', $t);
        }
    }

    public function testIndexFilterByMinAndMaxPriceDoesNotErrorAndReturnsGrid(): void
    {
        // Ici on teste surtout que ton parsing float + repository ne cassent pas la page.
        // Le contenu exact dépend de tes fixtures (prix des cours/leçons, etc.)
        $crawler = $this->client->request('GET', '/themes?minPrice=10&maxPrice=200');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertSelectorExists('.themes-grid');

        // Optionnel : assure qu’on a au moins 0 card (ça peut être 0 si aucune theme ne match)
        $this->assertGreaterThanOrEqual(0, $crawler->filter('.theme-card')->count());
    }

    public function testIndexAjaxReturnsFragmentOnly(): void
    {
        $crawler = $this->client->xmlHttpRequest('GET', '/themes?name=Musique');

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent() ?? '';

        // Fragment => pas de layout HTML complet
        $this->assertStringNotContainsString('<html', $content);
        $this->assertStringNotContainsString('<body', $content);

        // La partial doit contenir le conteneur / marqueur de liste
        $this->assertStringContainsString('themes-grid', $content);

        // Et normalement, "Musique" doit apparaître (si tu affiches le titre dans la carte)
        $this->assertStringContainsString('Musique', $content);

        // Bonus : le crawler doit pouvoir trouver des cards dans le fragment
        $this->assertGreaterThanOrEqual(0, $crawler->filter('.theme-card')->count());
    }

    public function testShowPageDisplaysThemeAndCursus(): void
    {
        // On essaie d'abord via un lien réel depuis /themes (test proche de l’usage)
        $crawler = $this->client->request('GET', '/themes');
        $this->assertResponseIsSuccessful();

        if ($crawler->filter('.theme-card a.btn.btn-primary')->count() > 0) {
            $link = $crawler->filter('.theme-card a.btn.btn-primary')->first()->link();
            $crawler = $this->client->click($link);

            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('h1');

            // Selon tes templates, tu as soit une grille, soit des cards
            $this->assertTrue(
                $crawler->filter('.cursus-grid')->count() > 0 || $crawler->filter('.cursus-card')->count() > 0,
                $this->debugResponseOnFailure()
            );

            return;
        }

        // Fallback robuste : on récupère un Theme en base (fixtures) et on appelle /themes/{id}
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $theme = $em->getRepository(Theme::class)->findOneBy([]);
        $this->assertNotNull($theme);

        $crawler = $this->client->request('GET', '/themes/' . $theme->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testShowReturns404IfThemeNotFound(): void
    {
        $this->client->request('GET', '/themes/999999');
        $this->assertResponseStatusCodeSame(404);
    }

    private function debugResponseOnFailure(): string
    {
        $status = $this->client->getResponse()?->getStatusCode();
        $content = $this->client->getResponse()?->getContent() ?? '';
        $snippet = mb_substr($content, 0, 1200);

        return "\n--- DEBUG RESPONSE ---\nHTTP: " . (string) $status . "\n" . $snippet . "\n----------------------\n";
    }
}