<?php

namespace App\Tests\Controller;

use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ThemeControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get()
            ->loadFixtures([
                \App\DataFixtures\ThemeFixtures::class,
            ]);
    }

    public function testIndexPageDisplaysThemes(): void
    {
        $crawler = $this->client->request('GET', '/themes');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Nos Thèmes');
        $this->assertSelectorExists('.themes-grid');
        $this->assertSelectorExists('.theme-card');

        // "Musique" doit être présent dans au moins un h2
        $titles = $crawler->filter('.theme-card h2')->each(
            fn ($node) => trim($node->text())
        );
        $this->assertContains('Musique', $titles);

        // Tes fixtures créent 4 thèmes
        $this->assertCount(4, $crawler->filter('.theme-card'));

        // Lien vers show
        $this->assertSelectorExists('.theme-card a.btn.btn-primary');
        $this->assertSelectorExists('a[href^="/themes/"]');
    }

    public function testShowPageDisplaysThemeAndCursus(): void
    {
        $crawler = $this->client->request('GET', '/themes');
        $this->assertResponseIsSuccessful();

        $this->assertGreaterThan(0, $crawler->filter('.theme-card a.btn.btn-primary')->count());
        $link = $crawler->filter('.theme-card a.btn.btn-primary')->first()->link();
        $this->client->click($link);

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('h1');
        $this->assertSelectorTextContains('h2', 'Cursus disponibles');
        $this->assertSelectorExists('.cursus-grid');
        $this->assertSelectorExists('.cursus-card');

        $this->assertSelectorExists('a.btn-secondary'); // détails
        $this->assertSelectorExists('a.btn');           // ajouter au panier
    }

    public function testShowReturns404IfThemeNotFound(): void
    {
        $this->client->request('GET', '/themes/999999');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testIndexAjaxReturnsFragmentOnly(): void
    {
        $this->client->request(
            'GET',
            '/themes?name=Musique',
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );

        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent() ?? '';

        // Fragment uniquement (pas de layout)
        $this->assertStringNotContainsString('<html', $content);
        $this->assertStringNotContainsString('<body', $content);

        $this->assertStringContainsString('Musique', $content);
    }

    public function testIndexFilterByMinPrice(): void
    {
        $this->client->request('GET', '/themes?minPrice=55');
        $this->assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent() ?? '';

        $this->assertStringContainsString('Informatique', $html);
        $this->assertStringNotContainsString('Musique', $html);
        $this->assertStringNotContainsString('Cuisine', $html);
        $this->assertStringNotContainsString('Jardinage', $html);
    }

    public function testIndexFilterByMaxPrice(): void
    {
        $this->client->request('GET', '/themes?maxPrice=40');
        $this->assertResponseIsSuccessful();

        $html = $this->client->getResponse()->getContent() ?? '';

        // Seul Jardinage a un cursus <= 40
        $this->assertStringContainsString('Jardinage', $html);

        $this->assertStringNotContainsString('Cuisine', $html);
        $this->assertStringNotContainsString('Musique', $html);
        $this->assertStringNotContainsString('Informatique', $html);
    }
}