<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\Theme;
use App\Form\ThemeType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ThemeTypeTest extends TestCase
{
    public function testBuildFormAddsExpectedFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $builder->expects(self::exactly(3))
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

        $type = new ThemeType();
        $type->buildForm($builder, []);
    }

    public function testBuildFormDoesNotAddIsActiveField(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        /** @var array<int, array{0:string,1:string}> $added */
        $added = [];

        // On capture tous les add() et on vérifie ensuite qu'aucun n'est "isActive"
        $builder->method('add')->willReturnCallback(function (string $name, string $type, array $options) use (&$added, $builder) {
            $added[] = [$name, $type];

            return $builder;
        });

        $type = new ThemeType();
        $type->buildForm($builder, []);

        $fieldNames = array_map(static fn(array $row) => $row[0], $added);

        self::assertContains('name', $fieldNames);
        self::assertContains('description', $fieldNames);
        self::assertContains('image', $fieldNames);
        self::assertNotContains('isActive', $fieldNames);
    }

    public function testConfigureOptionsSetsDataClass(): void
    {
        $resolver = new OptionsResolver();

        $type = new ThemeType();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        self::assertArrayHasKey('data_class', $options);
        self::assertSame(Theme::class, $options['data_class']);
    }
}