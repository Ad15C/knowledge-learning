<?php

namespace App\Tests\Entity;

use App\Entity\Cursus;
use App\Entity\Lesson;
use PHPUnit\Framework\TestCase;

class LessonTest extends TestCase
{
    public function testGettersSetters(): void
    {
        $lesson = new Lesson();

        $lesson->setTitle('Ma leçon');
        self::assertSame('Ma leçon', $lesson->getTitle());

        $lesson->setPrice(12);
        self::assertSame('12.00', $lesson->getPrice());

        $lesson->setPrice(12.3456);
        self::assertSame('12.35', $lesson->getPrice());

        $lesson->setPrice('19.9');
        self::assertSame('19.90', $lesson->getPrice());

        $lesson->setPrice(null);
        self::assertNull($lesson->getPrice());

        $lesson->setPrice('');
        self::assertNull($lesson->getPrice());

        $lesson->setFiche("Ligne 1<br><br>Ligne 2");
        self::assertSame("Ligne 1<br><br>Ligne 2", $lesson->getFiche());

        $lesson->setVideoUrl('https://example.com/video');
        self::assertSame('https://example.com/video', $lesson->getVideoUrl());

        $lesson->setImage('uploads/lesson.png');
        self::assertSame('uploads/lesson.png', $lesson->getImage());
    }

    public function testDefaults(): void
    {
        $lesson = new Lesson();

        self::assertNull($lesson->getId());
        self::assertNull($lesson->getTitle());
        self::assertNull($lesson->getPrice());
        self::assertNull($lesson->getCursus());
        self::assertNull($lesson->getFiche());
        self::assertNull($lesson->getVideoUrl());
        self::assertNull($lesson->getImage());
        self::assertTrue($lesson->isActive());
        self::assertFalse($lesson->isVisibleInCatalog());
    }

    public function testTitleIsTrimmed(): void
    {
        $lesson = new Lesson();

        $lesson->setTitle('   Ma leçon trimée   ');

        self::assertSame('Ma leçon trimée', $lesson->getTitle());
    }

    public function testCursusRelation(): void
    {
        $lesson = new Lesson();
        $cursus = $this->createMock(Cursus::class);

        $lesson->setCursus($cursus);
        self::assertSame($cursus, $lesson->getCursus());

        $lesson->setCursus(null);
        self::assertNull($lesson->getCursus());
    }

    public function testNullableFieldsCanBeSetToNull(): void
    {
        $lesson = new Lesson();

        $lesson->setFiche('Une fiche');
        $lesson->setVideoUrl('https://example.com/video');
        $lesson->setImage('image.png');

        $lesson->setFiche(null);
        $lesson->setVideoUrl(null);
        $lesson->setImage(null);

        self::assertNull($lesson->getFiche());
        self::assertNull($lesson->getVideoUrl());
        self::assertNull($lesson->getImage());
    }

    public function testSetIsActive(): void
    {
        $lesson = new Lesson();

        self::assertTrue($lesson->isActive());

        $lesson->setIsActive(false);
        self::assertFalse($lesson->isActive());

        $lesson->setIsActive(true);
        self::assertTrue($lesson->isActive());
    }

    public function testIsVisibleInCatalogWhenNoCursus(): void
    {
        $lesson = new Lesson();
        $lesson->setIsActive(true);

        self::assertFalse($lesson->isVisibleInCatalog());
    }

    public function testIsVisibleInCatalogWhenLessonInactive(): void
    {
        $lesson = new Lesson();

        $cursus = $this->createMock(Cursus::class);
        $cursus->method('isVisibleInCatalog')->willReturn(true);

        $lesson->setCursus($cursus);
        $lesson->setIsActive(false);

        self::assertFalse($lesson->isVisibleInCatalog());
    }

    public function testIsVisibleInCatalogWhenCursusNotVisible(): void
    {
        $lesson = new Lesson();

        $cursus = $this->createMock(Cursus::class);
        $cursus->method('isVisibleInCatalog')->willReturn(false);

        $lesson->setCursus($cursus);
        $lesson->setIsActive(true);

        self::assertFalse($lesson->isVisibleInCatalog());
    }

    public function testIsVisibleInCatalogWhenLessonAndCursusVisible(): void
    {
        $lesson = new Lesson();

        $cursus = $this->createMock(Cursus::class);
        $cursus->method('isVisibleInCatalog')->willReturn(true);

        $lesson->setCursus($cursus);
        $lesson->setIsActive(true);

        self::assertTrue($lesson->isVisibleInCatalog());
    }
}