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
        self::assertEqualsWithDelta(12.00, $lesson->getPrice(), 0.0001);

        $lesson->setPrice(12.3456);
        self::assertEqualsWithDelta(12.35, $lesson->getPrice(), 0.0001);

        $lesson->setFiche("Ligne 1<br><br>Ligne 2");
        self::assertSame("Ligne 1<br><br>Ligne 2", $lesson->getFiche());

        $lesson->setVideoUrl('https://example.com/video');
        self::assertSame('https://example.com/video', $lesson->getVideoUrl());

        $lesson->setImage('uploads/lesson.png');
        self::assertSame('uploads/lesson.png', $lesson->getImage());
    }
    
    public function testCursusRelation(): void
    {
        $lesson = new Lesson();
        $cursus = new Cursus();

        $lesson->setCursus($cursus);
        self::assertSame($cursus, $lesson->getCursus());
    }

    public function testPriceNullByDefault(): void
    {
        $lesson = new Lesson();
        self::assertNull($lesson->getPrice());
    }

    public function testIdIsInitiallyNull(): void
    {
        $lesson = new Lesson();
        self::assertNull($lesson->getId());
    }

}