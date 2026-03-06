<?php

namespace App\Tests\Entity;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use PHPUnit\Framework\TestCase;

class CursusTest extends TestCase
{
    public function testCursusProperties(): void
    {
        $cursus = new Cursus();

        $cursus->setName('Cursus PHP');
        self::assertSame('Cursus PHP', $cursus->getName());
        self::assertSame('Cursus PHP', (string) $cursus);

        $cursus->setPrice(199.99);
        self::assertSame('199.99', $cursus->getPrice());

        $cursus->setPrice('250');
        self::assertSame('250.00', $cursus->getPrice());

        $cursus->setDescription('Apprenez PHP de A à Z');
        self::assertSame('Apprenez PHP de A à Z', $cursus->getDescription());

        $cursus->setImage('cursus.jpg');
        self::assertSame('cursus.jpg', $cursus->getImage());

        self::assertTrue($cursus->isActive());
        $cursus->setIsActive(false);
        self::assertFalse($cursus->isActive());
    }

    public function testDefaults(): void
    {
        $cursus = new Cursus();

        self::assertNull($cursus->getId());
        self::assertSame('0.00', $cursus->getPrice());
        self::assertTrue($cursus->isActive());
        self::assertCount(0, $cursus->getLessons());
        self::assertFalse($cursus->isVisibleInCatalog());
    }

    public function testThemeRelation(): void
    {
        $cursus = new Cursus();

        $theme = $this->createMock(Theme::class);
        $cursus->setTheme($theme);

        self::assertSame($theme, $cursus->getTheme());
    }

    public function testAddLessonRelation(): void
    {
        $cursus = new Cursus();

        $lesson = $this->createMock(Lesson::class);
        $lesson->expects(self::once())
            ->method('setCursus')
            ->with($cursus);

        $cursus->addLesson($lesson);

        self::assertCount(1, $cursus->getLessons());
        self::assertTrue($cursus->getLessons()->contains($lesson));
    }

    public function testRemoveLessonRelation(): void
    {
        $cursus = new Cursus();

        $lesson = $this->createMock(Lesson::class);

        $lesson->expects(self::exactly(2))
            ->method('setCursus')
            ->withConsecutive(
                [$cursus],
                [null]
            );

        $lesson->expects(self::once())
            ->method('getCursus')
            ->willReturn($cursus);

        $cursus->addLesson($lesson);
        $cursus->removeLesson($lesson);

        self::assertCount(0, $cursus->getLessons());
        self::assertFalse($cursus->getLessons()->contains($lesson));
    }

    public function testIsVisibleInCatalogWhenThemeActiveAndCursusActive(): void
    {
        $cursus = new Cursus();

        $theme = $this->createMock(Theme::class);
        $theme->method('isActive')->willReturn(true);

        $cursus->setTheme($theme);
        $cursus->setIsActive(true);

        self::assertTrue($cursus->isVisibleInCatalog());
    }

    public function testIsVisibleInCatalogWhenCursusInactive(): void
    {
        $cursus = new Cursus();

        $theme = $this->createMock(Theme::class);
        $theme->method('isActive')->willReturn(true);

        $cursus->setTheme($theme);
        $cursus->setIsActive(false);

        self::assertFalse($cursus->isVisibleInCatalog());
    }

    public function testIsVisibleInCatalogWhenThemeInactive(): void
    {
        $cursus = new Cursus();

        $theme = $this->createMock(Theme::class);
        $theme->method('isActive')->willReturn(false);

        $cursus->setTheme($theme);
        $cursus->setIsActive(true);

        self::assertFalse($cursus->isVisibleInCatalog());
    }
}