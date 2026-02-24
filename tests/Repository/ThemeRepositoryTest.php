<?php

namespace App\Tests\Integration\Repository;

use App\Repository\ThemeRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ThemeRepositoryTest extends KernelTestCase
{
    private $databaseTool;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $this->databaseTool->loadFixtures([
            \App\DataFixtures\ThemeFixtures::class,
        ]);
    }

    public function testFindThemesWithNoFilters(): void
    {
        $repo = static::getContainer()->get(ThemeRepository::class);

        $themes = $repo->findThemesWithFilters();
        $this->assertNotEmpty($themes);

        // Vérifie que les cursus/lessons sont bien préchargés (leftJoin + addSelect)
        $this->assertNotEmpty($themes[0]->getCursus());
    }

    public function testFilterByName(): void
    {
        $repo = static::getContainer()->get(ThemeRepository::class);

        $themes = $repo->findThemesWithFilters('Musique', null, null);
        $this->assertCount(1, $themes);
        $this->assertSame('Musique', $themes[0]->getName());
    }

    public function testFilterByMinMaxPrice(): void
    {
        $repo = static::getContainer()->get(ThemeRepository::class);

        $themes = $repo->findThemesWithFilters(null, 55, null); // minPrice sur cursus.price
        // Devrait retourner Informatique (cursus 60), et pas Musique (50), Cuisine (44/48), Jardinage (30)
        $this->assertNotEmpty($themes);
        $names = array_map(fn($t) => $t->getName(), $themes);
        $this->assertContains('Informatique', $names);
        $this->assertNotContains('Jardinage', $names);
    }
}