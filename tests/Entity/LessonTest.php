<?php

namespace App\Tests\Entity;

use App\Entity\Lesson;
use App\Entity\Cursus;
use PHPUnit\Framework\TestCase;

class LessonTest extends TestCase
{
    public function testGettersSetters(): void
    {
        $lesson = new Lesson();

        $lesson->setTitle('Ma leçon');
        self::assertSame('Ma leçon', $lesson->getTitle());

        $lesson->setPrice(12);
        self::assertEquals(12.00, $lesson->getPrice());

        $lesson->setPrice(12.3456);
        self::assertEquals(12.35, $lesson->getPrice());

        // nouveaux cas utiles avec la nouvelle logique
        $lesson->setPrice(null);
        self::assertEquals(0.00, $lesson->getPrice());

        $lesson->setPrice('');
        self::assertEquals(0.00, $lesson->getPrice());

        $lesson->setPrice('19.9');
        self::assertEquals(19.90, $lesson->getPrice());

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
        self::assertEquals(0.00, $lesson->getPrice()); // plus null, car '0.00' par défaut
        self::assertTrue($lesson->isActive());
        self::assertFalse($lesson->isPubliclyAccessible(), 'Without cursus, lesson cannot be publicly accessible.');
    }

    public function testCursusRelation(): void
    {
        $lesson = new Lesson();
        $cursus = $this->createMock(Cursus::class);

        $lesson->setCursus($cursus);

        self::assertSame($cursus, $lesson->getCursus());
    }

    public function testIsPubliclyAccessibleWhenLessonInactive(): void
    {
        $lesson = new Lesson();

        $cursus = $this->createMock(Cursus::class);
        $cursus->method('isPubliclyAccessible')->willReturn(true);

        $lesson->setCursus($cursus);
        $lesson->setIsActive(false);

        self::assertFalse($lesson->isPubliclyAccessible());
    }

    public function testIsPubliclyAccessibleWhenCursusNotAccessible(): void
    {
        $lesson = new Lesson();

        $cursus = $this->createMock(Cursus::class);
        $cursus->method('isPubliclyAccessible')->willReturn(false);

        $lesson->setCursus($cursus);
        $lesson->setIsActive(true);

        self::assertFalse($lesson->isPubliclyAccessible());
    }

    public function testIsPubliclyAccessibleWhenLessonAndCursusAccessible(): void
    {
        $lesson = new Lesson();

        $cursus = $this->createMock(Cursus::class);
        $cursus->method('isPubliclyAccessible')->willReturn(true);

        $lesson->setCursus($cursus);
        $lesson->setIsActive(true);

        self::assertTrue($lesson->isPubliclyAccessible());
    }
}