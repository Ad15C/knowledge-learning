<?php

namespace App\Tests\Controller;

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
            ->loadFixtures([
                \App\DataFixtures\ThemeFixtures::class,
            ]);
    }

    public function testIndexPageDisplaysThemes(): void
    {
        $crawler = $this->client->request('GET', '/themes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertSelectorExists('.themes-grid');
        $this->assertGreaterThan(0, $crawler->filter('.theme-card')->count());

        $titles = $crawler->filter('.theme-card h2')->each(fn ($node) => trim($node->text()));
        $this->assertContains('Musique', $titles);

        $this->assertGreaterThan(0, $crawler->filter('.theme-card a.btn.btn-primary')->count());
    }

    public function testShowPageDisplaysThemeAndCursus(): void
    {
        $crawler = $this->client->request('GET', '/themes');
        $this->assertResponseIsSuccessful();

        $this->assertGreaterThan(0, $crawler->filter('.theme-card a.btn.btn-primary')->count());

        $link = $crawler->filter('.theme-card a.btn.btn-primary')->first()->link();
        $crawler = $this->client->click($link);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');

        $this->assertTrue(
            $crawler->filter('.cursus-grid')->count() > 0 || $crawler->filter('.cursus-card')->count() > 0,
            $this->debugResponseOnFailure()
        );
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

        $this->assertStringNotContainsString('<html', $content);
        $this->assertStringNotContainsString('<body', $content);
        $this->assertStringContainsString('themes-grid', $content);
        $this->assertStringContainsString('Musique', $content);
    }

    private function debugResponseOnFailure(): string
    {
        $status = $this->client->getResponse()?->getStatusCode();
        $content = $this->client->getResponse()?->getContent() ?? '';
        $snippet = mb_substr($content, 0, 1200);

        return "\n--- DEBUG RESPONSE ---\nHTTP: " . (string)$status . "\n" . $snippet . "\n----------------------\n";
    }
}