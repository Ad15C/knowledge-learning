<?php

namespace App\Tests\Entity;

use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\PurchaseItem;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class LessonValidatedTest extends TestCase
{
    public function testDefaultsOnConstruct(): void
    {
        $lv = new LessonValidated();

        $this->assertInstanceOf(\DateTimeImmutable::class, $lv->getValidatedAt());
        $this->assertTrue($lv->isCompleted());
        $this->assertNull($lv->getUser());
        $this->assertNull($lv->getLesson());
        $this->assertNull($lv->getPurchaseItem());
    }

    public function testSetUserAndLessonAndPurchaseItem(): void
    {
        $lv = new LessonValidated();

        $user = new User();
        $lesson = new Lesson();
        $pi = new PurchaseItem();

        $lv->setUser($user)->setLesson($lesson)->setPurchaseItem($pi);

        $this->assertSame($user, $lv->getUser());
        $this->assertSame($lesson, $lv->getLesson());
        $this->assertSame($pi, $lv->getPurchaseItem());
    }

    public function testMarkCompletedRefreshesValidatedAt(): void
    {
        $lv = new LessonValidated();
        $before = $lv->getValidatedAt();

        $this->assertSame($lv, $lv->markCompleted());
        $after = $lv->getValidatedAt();

        $this->assertTrue($lv->isCompleted());
        $this->assertInstanceOf(\DateTimeImmutable::class, $after);

        // robuste : on compare la référence objet (nouvelle instance)
        $this->assertNotSame($before, $after);
    }
}