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
        self::assertInstanceOf(\DateTimeImmutable::class, $theme->getCreatedAt());
        self::assertCount(0, $theme->getCursus());
        self::assertTrue($theme->isActive());
    }

    public function testSettersAndGetters(): void
    {
        $theme = new Theme();

        self::assertSame($theme, $theme->setName('  Musique  '));
        self::assertSame($theme, $theme->setDescription('desc'));
        self::assertSame($theme, $theme->setImage('img.jpg'));

        self::assertSame('Musique', $theme->getName());
        self::assertSame('desc', $theme->getDescription());
        self::assertSame('img.jpg', $theme->getImage());
    }

    public function testSetNameAcceptsNull(): void
    {
        $theme = new Theme();

        $theme->setName(null);

        self::assertNull($theme->getName());
    }

    public function testAddCursusMaintainsOwningSide(): void
    {
        $theme = new Theme();
        $cursus = new Cursus();

        $theme->addCursus($cursus);

        self::assertCount(1, $theme->getCursus());
        self::assertTrue($theme->getCursus()->contains($cursus));
        self::assertSame($theme, $cursus->getTheme());
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
        self::assertNull($cursus->getTheme());
    }

    public function testSetCreatedAt(): void
    {
        $theme = new Theme();
        $date = new \DateTimeImmutable('2024-01-15 10:30:00');

        $theme->setCreatedAt($date);

        self::assertSame($date, $theme->getCreatedAt());
    }

    public function testSetIsActive(): void
    {
        $theme = new Theme();

        $theme->setIsActive(false);
        self::assertFalse($theme->isActive());

        $theme->setIsActive(true);
        self::assertTrue($theme->isActive());
    }

    public function testToStringReturnsName(): void
    {
        $theme = new Theme();
        $theme->setName('Piano');

        self::assertSame('Piano', (string) $theme);
    }
}