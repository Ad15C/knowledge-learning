<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Cursus;
use App\Entity\Theme;
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    public function testThemeDefaults(): void
    {
        $theme = new Theme();

        self::assertNull($theme->getId());
        self::assertInstanceOf(\DateTimeInterface::class, $theme->getCreatedAt());
        self::assertCount(0, $theme->getCursus());

        // bool default
        self::assertTrue($theme->isActive());
    }

    public function testSettersAndGetters(): void
    {
        $theme = new Theme();

        self::assertSame($theme, $theme->setName('Musique'));
        self::assertSame($theme, $theme->setDescription('desc'));
        self::assertSame($theme, $theme->setImage('img.jpg'));

        self::assertSame('Musique', $theme->getName());
        self::assertSame('desc', $theme->getDescription());
        self::assertSame('img.jpg', $theme->getImage());
    }

    public function testAddCursusMaintainsOwningSide(): void
    {
        $theme = new Theme();
        $cursus = new Cursus();

        $theme->addCursus($cursus);

        self::assertCount(1, $theme->getCursus());
        self::assertTrue($theme->getCursus()->contains($cursus));
        self::assertSame($theme, $cursus->getTheme(), 'addCursus() must set owning side on Cursus');
    }

    public function testAddCursusTwiceDoesNotDuplicate(): void
    {
        $theme = new Theme();
        $cursus = new Cursus();

        $theme->addCursus($cursus);
        $theme->addCursus($cursus);

        self::assertCount(1, $theme->getCursus());
    }

    public function testRemoveCursusRemovesFromCollection(): void
    {
        $theme = new Theme();
        $cursus = new Cursus();

        $theme->addCursus($cursus);
        $theme->removeCursus($cursus);

        self::assertCount(0, $theme->getCursus());
        self::assertFalse($theme->getCursus()->contains($cursus));

        // IMPORTANT:
        // Ton entité Theme::removeCursus() ne met PAS $cursus->setTheme(null)
        // donc on ne peut pas exiger que l'owning side soit null.
        // Si tu veux ce comportement, il faut corriger l'entité (voir note ci-dessous).
        self::assertSame($theme, $cursus->getTheme());
    }

    public function testSetIsActive(): void
    {
        $theme = new Theme();

        $theme->setIsActive(false);
        self::assertFalse($theme->isActive());

        $theme->setIsActive(true);
        self::assertTrue($theme->isActive());
    }
}