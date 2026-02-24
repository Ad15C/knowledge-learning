<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Theme;
use App\Entity\Cursus;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    public function testThemeDefaults(): void
    {
        $theme = new Theme();

        $this->assertNull($theme->getId());
        $this->assertInstanceOf(\DateTimeInterface::class, $theme->getCreatedAt());
        $this->assertCount(0, $theme->getCursus());
    }

    public function testSettersAndGetters(): void
    {
        $theme = new Theme();
        $theme->setName('Musique')
            ->setDescription('desc')
            ->setImage('img.jpg');

        $this->assertSame('Musique', $theme->getName());
        $this->assertSame('desc', $theme->getDescription());
        $this->assertSame('img.jpg', $theme->getImage());
    }

    public function testAddRemoveCursusMaintainsOwningSide(): void
    {
        $theme = new Theme();
        $cursus = new Cursus();

        $theme->addCursus($cursus);
        $this->assertCount(1, $theme->getCursus());
        $this->assertSame($theme, $cursus->getTheme());

        $theme->removeCursus($cursus);
        $this->assertCount(0, $theme->getCursus());
        $this->assertNull($cursus->getTheme());
    }
}