<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Cursus;
use App\Entity\Theme;
use App\Form\CursusType;
use App\Repository\ThemeRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class CursusTypeTest extends TestCase
{
    public function testBuildFormAddsExpectedFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $builder->expects(self::exactly(5))
            ->method('add')
            ->withConsecutive(
                [
                    'name',
                    TextType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('Nom', $options['label'] ?? null);
                        self::assertSame('', $options['empty_data'] ?? null);

                        return true;
                    }),
                ],
                [
                    'theme',
                    EntityType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame(Theme::class, $options['class'] ?? null);
                        self::assertSame('name', $options['choice_label'] ?? null);
                        self::assertSame('Thème', $options['label'] ?? null);
                        self::assertSame('— Choisir un thème —', $options['placeholder'] ?? null);
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

                        return true;
                    }),
                ],
                [
                    'description',
                    TextareaType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('Description', $options['label'] ?? null);
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

        $type = new CursusType();
        $type->buildForm($builder, ['data' => new Cursus()]);
    }

    public function testThemeQueryBuilderFiltersOnlyActiveThemesWhenNoCurrentTheme(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $captured = [];
        $builder->method('add')->willReturnCallback(function (string $name, string $type, array $options) use (&$captured, $builder) {
            $captured[$name] = [$type, $options];

            return $builder;
        });

        $type = new CursusType();
        $type->buildForm($builder, ['data' => new Cursus()]);

        self::assertArrayHasKey('theme', $captured);
        [$fieldType, $options] = $captured['theme'];
        self::assertSame(EntityType::class, $fieldType);
        self::assertIsCallable($options['query_builder']);

        $repo = $this->createMock(ThemeRepository::class);
        $qb   = $this->createMock(QueryBuilder::class);

        $repo->expects(self::once())
            ->method('createQueryBuilder')
            ->with('t')
            ->willReturn($qb);

        $qb->expects(self::once())->method('distinct')->willReturnSelf();
        $qb->expects(self::once())->method('orderBy')->with('t.name', 'ASC')->willReturnSelf();

        // No current theme -> only active themes
        $qb->expects(self::once())->method('andWhere')->with('t.isActive = true')->willReturnSelf();
        $qb->expects(self::never())->method('setParameter');

        $returned = ($options['query_builder'])($repo);

        self::assertSame($qb, $returned);
    }

    public function testThemeQueryBuilderIncludesCurrentThemeEvenIfInactive(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $captured = [];
        $builder->method('add')->willReturnCallback(function (string $name, string $type, array $options) use (&$captured, $builder) {
            $captured[$name] = [$type, $options];

            return $builder;
        });

        // Cursus with a current Theme (id = 123)
        $currentTheme = new Theme();
        $this->forceSetId($currentTheme, 123);

        $cursus = new Cursus();
        if (method_exists($cursus, 'setTheme')) {
            $cursus->setTheme($currentTheme);
        } else {
            $this->fail('Cursus::setTheme() not found. Update the test to set the theme on Cursus.');
        }

        $type = new CursusType();
        $type->buildForm($builder, ['data' => $cursus]);

        self::assertArrayHasKey('theme', $captured);
        [, $options] = $captured['theme'];
        self::assertIsCallable($options['query_builder']);

        $repo = $this->createMock(ThemeRepository::class);
        $qb   = $this->createMock(QueryBuilder::class);

        $repo->expects(self::once())
            ->method('createQueryBuilder')
            ->with('t')
            ->willReturn($qb);

        $qb->expects(self::once())->method('distinct')->willReturnSelf();
        $qb->expects(self::once())->method('orderBy')->with('t.name', 'ASC')->willReturnSelf();

        // Expr::orX MUST return an Orx (typed)
        $orx = new Orx(['t.isActive = true', 't.id = :currentThemeId']);

        $expr = $this->createMock(Expr::class);
        $expr->expects(self::once())
            ->method('orX')
            ->with('t.isActive = true', 't.id = :currentThemeId')
            ->willReturn($orx);

        $qb->expects(self::once())
            ->method('expr')
            ->willReturn($expr);

        $qb->expects(self::once())
            ->method('andWhere')
            ->with($orx)
            ->willReturnSelf();

        $qb->expects(self::once())
            ->method('setParameter')
            ->with('currentThemeId', 123)
            ->willReturnSelf();

        $returned = ($options['query_builder'])($repo);

        self::assertSame($qb, $returned);
    }

    private function forceSetId(object $entity, int $id): void
    {
        $ref = new \ReflectionClass($entity);

        if (!$ref->hasProperty('id')) {
            $this->fail(sprintf('Property "id" not found on %s; adjust forceSetId() for your entity.', $ref->getName()));
        }

        $prop = $ref->getProperty('id');
        $prop->setAccessible(true);
        $prop->setValue($entity, $id);
    }
}