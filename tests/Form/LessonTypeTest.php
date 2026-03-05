<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Form\LessonType;
use App\Repository\CursusRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class LessonTypeTest extends TestCase
{
    public function testBuildFormAddsExpectedFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $builder->expects(self::exactly(6))
            ->method('add')
            ->withConsecutive(
                [
                    'title',
                    TextType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('Titre', $options['label'] ?? null);
                        self::assertSame('', $options['empty_data'] ?? null);

                        return true;
                    }),
                ],
                [
                    'cursus',
                    EntityType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame(Cursus::class, $options['class'] ?? null);
                        self::assertSame('name', $options['choice_label'] ?? null);
                        self::assertSame('Cursus', $options['label'] ?? null);
                        self::assertSame('— Choisir un cursus —', $options['placeholder'] ?? null);
                        self::assertArrayHasKey('query_builder', $options);
                        self::assertIsCallable($options['query_builder']);

                        return true;
                    }),
                ],
                [
                    'price',
                    MoneyType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('Prix', $options['label'] ?? null);
                        self::assertSame('EUR', $options['currency'] ?? null);
                        self::assertTrue($options['required'] ?? false);

                        self::assertArrayHasKey('constraints', $options);
                        self::assertIsArray($options['constraints']);
                        self::assertNotEmpty($options['constraints']);

                        $found = false;
                        foreach ($options['constraints'] as $constraint) {
                            if ($constraint instanceof NotBlank) {
                                $found = true;
                                // Symfony 6+ uses "message" property on the constraint
                                self::assertSame('Le prix est obligatoire.', $constraint->message);
                            }
                        }
                        self::assertTrue($found, 'Expected a NotBlank constraint on price.');

                        return true;
                    }),
                ],
                [
                    'fiche',
                    TextareaType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('Fiche', $options['label'] ?? null);
                        self::assertFalse($options['required'] ?? true);

                        return true;
                    }),
                ],
                [
                    'videoUrl',
                    TextType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('URL vidéo', $options['label'] ?? null);
                        self::assertFalse($options['required'] ?? true);

                        return true;
                    }),
                ],
                [
                    'image',
                    TextType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('Image (chemin/URL)', $options['label'] ?? null);
                        self::assertFalse($options['required'] ?? true);

                        return true;
                    }),
                ]
            )
            ->willReturn($builder);

        $type = new LessonType();
        $type->buildForm($builder, []);
    }

    public function testCursusQueryBuilderFiltersOnlyActiveWhenNoCurrentCursus(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        // buildForm captures query_builder option; we need builder->getData() in that closure
        $builder->expects(self::once())
            ->method('getData')
            ->willReturn(null);

        $captured = [];
        $builder->method('add')->willReturnCallback(function (string $name, string $type, array $options) use (&$captured, $builder) {
            $captured[$name] = [$type, $options];

            return $builder;
        });

        $type = new LessonType();
        $type->buildForm($builder, []);

        self::assertArrayHasKey('cursus', $captured);
        [, $options] = $captured['cursus'];
        self::assertIsCallable($options['query_builder']);

        $repo = $this->createMock(CursusRepository::class);
        $qb   = $this->createMock(QueryBuilder::class);

        $repo->expects(self::once())
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($qb);

        $qb->expects(self::once())
            ->method('orderBy')
            ->with('c.name', 'ASC')
            ->willReturnSelf();

        $qb->expects(self::once())
            ->method('andWhere')
            ->with('c.isActive = true')
            ->willReturnSelf();

        $qb->expects(self::never())->method('setParameter');

        $returned = ($options['query_builder'])($repo);

        self::assertSame($qb, $returned);
    }

    public function testCursusQueryBuilderIncludesCurrentCursusEvenIfInactive(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $cursus = $this->createMock(Cursus::class);
        $cursus->method('getId')->willReturn(42);

        /** @var Lesson&\PHPUnit\Framework\MockObject\MockObject $lesson */
        $lesson = $this->createMock(Lesson::class);
        $lesson->method('getCursus')->willReturn($cursus);

        $builder->expects(self::once())
            ->method('getData')
            ->willReturn($lesson);

        $captured = [];
        $builder->method('add')->willReturnCallback(function (string $name, string $type, array $options) use (&$captured, $builder) {
            $captured[$name] = [$type, $options];

            return $builder;
        });

        $type = new LessonType();
        $type->buildForm($builder, []);

        self::assertArrayHasKey('cursus', $captured);
        [, $options] = $captured['cursus'];
        self::assertIsCallable($options['query_builder']);

        $repo = $this->createMock(CursusRepository::class);
        $qb   = $this->createMock(QueryBuilder::class);

        $repo->expects(self::once())
            ->method('createQueryBuilder')
            ->with('c')
            ->willReturn($qb);

        $qb->expects(self::once())
            ->method('orderBy')
            ->with('c.name', 'ASC')
            ->willReturnSelf();

        $qb->expects(self::once())
            ->method('andWhere')
            ->with('(c.isActive = true OR c.id = :currentId)')
            ->willReturnSelf();

        $qb->expects(self::once())
            ->method('setParameter')
            ->with('currentId', 42)
            ->willReturnSelf();

        $returned = ($options['query_builder'])($repo);

        self::assertSame($qb, $returned);
    }
}