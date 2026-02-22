<?php

namespace App\Tests\Entity;

use App\Entity\Lesson;
use App\Entity\Cursus;
use PHPUnit\Framework\TestCase;

class LessonTest extends TestCase
{
    public function testLessonProperties()
    {
        $lesson = new Lesson();

        // --- Title ---
        $lesson->setTitle('Introduction à PHP');
        $this->assertSame('Introduction à PHP', $lesson->getTitle());

        // --- Price ---
        $lesson->setPrice(49.99);
        $this->assertSame(49.99, $lesson->getPrice());

        // --- Fiche ---
        $lesson->setFiche('Ceci est la fiche du cours');
        $this->assertSame('Ceci est la fiche du cours', $lesson->getFiche());

        // --- Video URL ---
        $lesson->setVideoUrl('https://example.com/video.mp4');
        $this->assertSame('https://example.com/video.mp4', $lesson->getVideoUrl());

        // --- Image ---
        $lesson->setImage('lesson.jpg');
        $this->assertSame('lesson.jpg', $lesson->getImage());
    }

    public function testCursusRelation()
    {
        $lesson = new Lesson();
        $cursus = new Cursus();

        // Associer un cursus
        $lesson->setCursus($cursus);
        $this->assertSame($cursus, $lesson->getCursus());

        // Supprimer l'association
        $lesson->setCursus(null);
        $this->assertNull($lesson->getCursus());
    }

    public function testIdIsInitiallyNull()
    {
        $lesson = new Lesson();
        $this->assertNull($lesson->getId());
    }
}