<?php

namespace App\Tests\Service;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\User;
use App\Service\LessonAccessService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class LessonAccessServiceTest extends TestCase
{
    public function testUserCanAccessLessonReturnsTrueForAdmin(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $service = new LessonAccessService($em);

        $user = $this->createMock(User::class);
        $lesson = $this->createMock(Lesson::class);

        $user->method('getRoles')->willReturn(['ROLE_USER', 'ROLE_ADMIN']);

        self::assertTrue($service->userCanAccessLesson($user, $lesson));
    }

    public function testUserCanAccessLessonReturnsTrueWhenPaidLessonOrCursusExists(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);
        $expr = $this->createMock(Expr::class);
        $orX = $this->createMock(Orx::class);

        $service = new LessonAccessService($em);

        $user = $this->createMock(User::class);
        $lesson = $this->createMock(Lesson::class);
        $cursus = $this->createMock(Cursus::class);

        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $lesson->method('getCursus')->willReturn($cursus);

        $em->expects(self::once())
            ->method('getRepository')
            ->with(PurchaseItem::class)
            ->willReturn($repository);

        $repository->expects(self::once())
            ->method('createQueryBuilder')
            ->with('pi')
            ->willReturn($qb);

        $qb->method('join')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();

        $qb->expects(self::once())
            ->method('expr')
            ->willReturn($expr);

        $expr->expects(self::once())
            ->method('orX')
            ->with('pi.lesson = :lesson', 'pi.cursus = :cursus')
            ->willReturn($orX);

        $qb->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(new \stdClass());

        self::assertTrue($service->userCanAccessLesson($user, $lesson));
    }

    public function testUserCanAccessLessonReturnsFalseWhenNoPaidLessonOrCursusExists(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);
        $expr = $this->createMock(Expr::class);
        $orX = $this->createMock(Orx::class);

        $service = new LessonAccessService($em);

        $user = $this->createMock(User::class);
        $lesson = $this->createMock(Lesson::class);
        $cursus = $this->createMock(Cursus::class);

        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $lesson->method('getCursus')->willReturn($cursus);

        $em->expects(self::once())
            ->method('getRepository')
            ->with(PurchaseItem::class)
            ->willReturn($repository);

        $repository->expects(self::once())
            ->method('createQueryBuilder')
            ->with('pi')
            ->willReturn($qb);

        $qb->method('join')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();

        $qb->expects(self::once())
            ->method('expr')
            ->willReturn($expr);

        $expr->expects(self::once())
            ->method('orX')
            ->with('pi.lesson = :lesson', 'pi.cursus = :cursus')
            ->willReturn($orX);

        $qb->expects(self::once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        self::assertFalse($service->userCanAccessLesson($user, $lesson));
    }

    public function testGetAccessibleLessonMapForCursusReturnsAllLessonsForAdmin(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $service = new LessonAccessService($em);

        $user = $this->createMock(User::class);
        $cursus = $this->createMock(Cursus::class);
        $lesson1 = $this->createMock(Lesson::class);
        $lesson2 = $this->createMock(Lesson::class);

        $user->method('getRoles')->willReturn(['ROLE_ADMIN']);

        $lesson1->method('getId')->willReturn(10);
        $lesson2->method('getId')->willReturn(20);

        $cursus->method('getLessons')->willReturn(new ArrayCollection([
            $lesson1,
            $lesson2,
        ]));

        $result = $service->getAccessibleLessonMapForCursus($user, $cursus);

        self::assertSame([
            10 => true,
            20 => true,
        ], $result);
    }

    public function testGetAccessibleLessonMapForCursusReturnsAllLessonsWhenWholeCursusPurchased(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);

        $qbCursus = $this->createMock(QueryBuilder::class);
        $queryCursus = $this->createMock(Query::class);

        $service = new LessonAccessService($em);

        $user = $this->createMock(User::class);
        $cursus = $this->createMock(Cursus::class);
        $lesson1 = $this->createMock(Lesson::class);
        $lesson2 = $this->createMock(Lesson::class);

        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $lesson1->method('getId')->willReturn(1);
        $lesson2->method('getId')->willReturn(2);

        $cursus->method('getLessons')->willReturn(new ArrayCollection([
            $lesson1,
            $lesson2,
        ]));

        $em->expects(self::once())
            ->method('getRepository')
            ->with(PurchaseItem::class)
            ->willReturn($repository);

        $repository->expects(self::once())
            ->method('createQueryBuilder')
            ->with('pi')
            ->willReturn($qbCursus);

        $qbCursus->method('join')->willReturnSelf();
        $qbCursus->method('andWhere')->willReturnSelf();
        $qbCursus->method('setParameter')->willReturnSelf();
        $qbCursus->method('setMaxResults')->willReturnSelf();

        $qbCursus->expects(self::once())
            ->method('getQuery')
            ->willReturn($queryCursus);

        $queryCursus->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(new \stdClass());

        $result = $service->getAccessibleLessonMapForCursus($user, $cursus);

        self::assertSame([
            1 => true,
            2 => true,
        ], $result);
    }

    public function testGetAccessibleLessonMapForCursusReturnsOnlyPurchasedLessonsWhenNoWholeCursusPurchase(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);

        $qbCursus = $this->createMock(QueryBuilder::class);
        $queryCursus = $this->createMock(Query::class);

        $qbLessons = $this->createMock(QueryBuilder::class);
        $queryLessons = $this->createMock(Query::class);

        $service = new LessonAccessService($em);

        $user = $this->createMock(User::class);
        $cursus = $this->createMock(Cursus::class);

        $lesson1 = $this->createMock(Lesson::class);
        $lesson2 = $this->createMock(Lesson::class);

        $item1 = $this->createMock(PurchaseItem::class);
        $item2 = $this->createMock(PurchaseItem::class);

        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $lesson1->method('getId')->willReturn(101);
        $lesson2->method('getId')->willReturn(202);

        $item1->method('getLesson')->willReturn($lesson1);
        $item2->method('getLesson')->willReturn($lesson2);

        $em->expects(self::exactly(2))
            ->method('getRepository')
            ->with(PurchaseItem::class)
            ->willReturn($repository);

        $repository->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with('pi')
            ->willReturnOnConsecutiveCalls($qbCursus, $qbLessons);

        $qbCursus->method('join')->willReturnSelf();
        $qbCursus->method('andWhere')->willReturnSelf();
        $qbCursus->method('setParameter')->willReturnSelf();
        $qbCursus->method('setMaxResults')->willReturnSelf();

        $qbCursus->expects(self::once())
            ->method('getQuery')
            ->willReturn($queryCursus);

        $queryCursus->expects(self::once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        $qbLessons->method('join')->willReturnSelf();
        $qbLessons->method('andWhere')->willReturnSelf();
        $qbLessons->method('setParameter')->willReturnSelf();

        $qbLessons->expects(self::once())
            ->method('getQuery')
            ->willReturn($queryLessons);

        $queryLessons->expects(self::once())
            ->method('getResult')
            ->willReturn([$item1, $item2]);

        $result = $service->getAccessibleLessonMapForCursus($user, $cursus);

        self::assertSame([
            101 => true,
            202 => true,
        ], $result);
    }

    public function testGetAccessibleLessonMapForCursusIgnoresPurchaseItemsWithoutLessonId(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repository = $this->createMock(EntityRepository::class);

        $qbCursus = $this->createMock(QueryBuilder::class);
        $queryCursus = $this->createMock(Query::class);

        $qbLessons = $this->createMock(QueryBuilder::class);
        $queryLessons = $this->createMock(Query::class);

        $service = new LessonAccessService($em);

        $user = $this->createMock(User::class);
        $cursus = $this->createMock(Cursus::class);

        $lessonWithoutId = $this->createMock(Lesson::class);
        $item = $this->createMock(PurchaseItem::class);

        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $lessonWithoutId->method('getId')->willReturn(null);
        $item->method('getLesson')->willReturn($lessonWithoutId);

        $em->expects(self::exactly(2))
            ->method('getRepository')
            ->with(PurchaseItem::class)
            ->willReturn($repository);

        $repository->expects(self::exactly(2))
            ->method('createQueryBuilder')
            ->with('pi')
            ->willReturnOnConsecutiveCalls($qbCursus, $qbLessons);

        $qbCursus->method('join')->willReturnSelf();
        $qbCursus->method('andWhere')->willReturnSelf();
        $qbCursus->method('setParameter')->willReturnSelf();
        $qbCursus->method('setMaxResults')->willReturnSelf();
        $qbCursus->method('getQuery')->willReturn($queryCursus);

        $queryCursus->method('getOneOrNullResult')->willReturn(null);

        $qbLessons->method('join')->willReturnSelf();
        $qbLessons->method('andWhere')->willReturnSelf();
        $qbLessons->method('setParameter')->willReturnSelf();
        $qbLessons->method('getQuery')->willReturn($queryLessons);

        $queryLessons->method('getResult')->willReturn([$item]);

        $result = $service->getAccessibleLessonMapForCursus($user, $cursus);

        self::assertSame([], $result);
    }
}