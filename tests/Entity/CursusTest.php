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
        self::assertSame('Cursus PHP', (string) $cursus); // __toString

        $cursus->setPrice(199.99);
        self::assertEquals(199.99, $cursus->getPrice());

        $cursus->setDescription('Apprenez PHP de A à Z');
        self::assertSame('Apprenez PHP de A à Z', $cursus->getDescription());

        $cursus->setImage('cursus.jpg');
        self::assertSame('cursus.jpg', $cursus->getImage());

        self::assertTrue($cursus->isActive());
        $cursus->setIsActive(false);
        self::assertFalse($cursus->isActive());
    }

    public function testThemeRelation(): void
    {
        $cursus = new Cursus();

        $theme = $this->createMock(Theme::class);
        $cursus->setTheme($theme);

        self::assertSame($theme, $cursus->getTheme());
    }

    public function testLessonRelation(): void
    {
        $cursus = new Cursus();

        // On mock Lesson pour éviter de devoir remplir title/price/cursus/etc.
        $lesson = $this->createMock(Lesson::class);

        // addLesson() appelle $lesson->setCursus($this)
        $lesson->expects(self::once())->method('setCursus')->with($cursus);

        $cursus->addLesson($lesson);

        self::assertCount(1, $cursus->getLessons());
        self::assertTrue($cursus->getLessons()->contains($lesson));

        // removeLesson
        $cursus->removeLesson($lesson);
        self::assertCount(0, $cursus->getLessons());
    }

    public function testIsPubliclyAccessibleDependsOnThemeAndActive(): void
    {
        $cursus = new Cursus();

        $theme = $this->createMock(Theme::class);
        $theme->method('isActive')->willReturn(true);

        $cursus->setTheme($theme);

        // actif + theme actif => true
        $cursus->setIsActive(true);
        self::assertTrue($cursus->isPubliclyAccessible());

        // cursus inactif => false
        $cursus->setIsActive(false);
        self::assertFalse($cursus->isPubliclyAccessible());

        // theme inactif => false
        $cursus->setIsActive(true);
        $theme2 = $this->createMock(Theme::class);
        $theme2->method('isActive')->willReturn(false);
        $cursus->setTheme($theme2);
        self::assertFalse($cursus->isPubliclyAccessible());
    }

    public function testIdInitiallyNull(): void
    {
        $cursus = new Cursus();
        self::assertNull($cursus->getId());
    }
}