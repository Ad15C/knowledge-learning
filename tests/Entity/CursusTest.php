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
        $this->assertSame('Cursus PHP', $cursus->getName());

        $cursus->setPrice(199.99);
        $this->assertEqualsWithDelta(199.99, $cursus->getPrice(), 0.0001);

        $cursus->setDescription('Apprenez PHP de A à Z');
        $this->assertSame('Apprenez PHP de A à Z', $cursus->getDescription());

        $cursus->setImage('cursus.jpg');
        $this->assertSame('cursus.jpg', $cursus->getImage());
    }

    public function testThemeRelation(): void
    {
        $cursus = new Cursus();
        $theme = new Theme();

        $cursus->setTheme($theme);
        $this->assertSame($theme, $cursus->getTheme());
    }

    public function testLessonRelation(): void
    {
        $cursus = new Cursus();
        $lesson = new Lesson();

        $cursus->addLesson($lesson);

        $this->assertTrue($cursus->getLessons()->contains($lesson));
        $this->assertSame($cursus, $lesson->getCursus());
    }

    public function testIdInitiallyNull(): void
    {
        $cursus = new Cursus();
        $this->assertNull($cursus->getId());
    }
}