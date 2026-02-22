<?php

namespace App\Tests\Entity;

use App\Entity\Theme;
use App\Entity\Cursus;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    public function testThemeProperties()
    {
        $theme = new Theme();

        // --- Name ---
        $theme->setName('Développement Web');
        $this->assertSame('Développement Web', $theme->getName());

        // --- Description ---
        $theme->setDescription('Cours de PHP, Symfony, JS...');
        $this->assertSame('Cours de PHP, Symfony, JS...', $theme->getDescription());

        // --- Image ---
        $theme->setImage('theme.jpg');
        $this->assertSame('theme.jpg', $theme->getImage());

        // --- CreatedAt ---
        $now = new \DateTime();
        $theme->setCreatedAt($now);
        $this->assertSame($now, $theme->getCreatedAt());
    }

    public function testCursusRelation()
    {
        $theme = new Theme();
        $cursus = new Cursus();

        // Ajouter un cursus
        $theme->addCursus($cursus);
        $this->assertContains($cursus, $theme->getCursus());
        $this->assertSame($theme, $cursus->getTheme());

        // Retirer un cursus
        $theme->removeCursus($cursus);
        $this->assertNotContains($cursus, $theme->getCursus());
        $this->assertNull($cursus->getTheme());
    }

    public function testIdInitiallyNull()
    {
        $theme = new Theme();
        $this->assertNull($theme->getId());
    }
}