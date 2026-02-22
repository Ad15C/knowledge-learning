<?php

namespace App\Tests\Entity;

use App\Entity\Cursus;
use App\Entity\Theme;
use App\Entity\Lesson;
use PHPUnit\Framework\TestCase;

class CursusTest extends TestCase
{
    public function testCursusProperties()
    {
        $cursus = new Cursus();

        // --- Name ---
        $cursus->setName('Cursus PHP');
        $this->assertSame('Cursus PHP', $cursus->getName());

        // --- Price ---
        $cursus->setPrice(199.99);
        $this->assertSame(199.99, $cursus->getPrice());

        // --- Description ---
        $cursus->setDescription('Apprenez PHP de A à Z');
        $this->assertSame('Apprenez PHP de A à Z', $cursus->getDescription());

        // --- Image ---
        $cursus->setImage('cursus.jpg');
        $this->assertSame('cursus.jpg', $cursus->getImage());
    }

    public function testThemeRelation()
    {
        $cursus = new Cursus();
        $theme = new Theme();

        $cursus->setTheme($theme);
        $this->assertSame($theme, $cursus->getTheme());
    }

    public function testLessonRelation()
    {
        $cursus = new Cursus();
        $lesson = new Lesson();

        $cursus->addLesson($lesson);
        $this->assertContains($lesson, $cursus->getLessons());
        $this->assertSame($cursus, $lesson->getCursus());

        $cursus->removeLesson($lesson);
        $this->assertNotContains($lesson, $cursus->getLessons());
        $this->assertNull($lesson->getCursus());
    }

    public function testIdInitiallyNull()
    {
        $cursus = new Cursus();
        $this->assertNull($cursus->getId());
    }
}