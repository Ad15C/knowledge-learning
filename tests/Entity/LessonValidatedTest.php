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

        self::assertNull($lv->getId(), 'ID should be null before persist');
        self::assertInstanceOf(\DateTimeImmutable::class, $lv->getValidatedAt());
        self::assertTrue($lv->isCompleted());

        self::assertNull($lv->getUser());
        self::assertNull($lv->getLesson());
        self::assertNull($lv->getPurchaseItem());
    }

    public function testSetUserAndLessonAndPurchaseItem(): void
    {
        $lv = new LessonValidated();

        $user = $this->createMock(User::class);
        $lesson = $this->createMock(Lesson::class);
        $pi = $this->createMock(PurchaseItem::class);

        self::assertSame($lv, $lv->setUser($user));
        self::assertSame($lv, $lv->setLesson($lesson));
        self::assertSame($lv, $lv->setPurchaseItem($pi));

        self::assertSame($user, $lv->getUser());
        self::assertSame($lesson, $lv->getLesson());
        self::assertSame($pi, $lv->getPurchaseItem());
    }

    public function testMarkCompletedRefreshesValidatedAtAndKeepsCompletedTrue(): void
    {
        $lv = new LessonValidated();

        // On force completed à true dès le construct, mais on vérifie quand même
        self::assertTrue($lv->isCompleted());

        $before = $lv->getValidatedAt();
        self::assertInstanceOf(\DateTimeImmutable::class, $before);

        // Petite pause pour éviter tout edge case ultra rare (même microseconde)
        usleep(1000);

        self::assertSame($lv, $lv->markCompleted());
        $after = $lv->getValidatedAt();

        self::assertTrue($lv->isCompleted());
        self::assertInstanceOf(\DateTimeImmutable::class, $after);

        // Nouvelle instance (robuste)
        self::assertNotSame($before, $after);

        // Et chronologiquement cohérent
        self::assertGreaterThanOrEqual($before->getTimestamp(), $after->getTimestamp());
    }
}