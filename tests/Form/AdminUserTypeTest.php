<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\Admin\UserType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AdminUserTypeTest extends TestCase
{
    public function testBuildFormAddsBaseFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $builder->expects(self::atLeast(3))
            ->method('add')
            ->withConsecutive(
                [
                    'firstName',
                    TextType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('Prénom', $options['label'] ?? null);
                        self::assertTrue($options['required'] ?? false);
                        return true;
                    }),
                ],
                [
                    'lastName',
                    TextType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('Nom', $options['label'] ?? null);
                        self::assertTrue($options['required'] ?? false);
                        return true;
                    }),
                ],
                [
                    'email',
                    EmailType::class,
                    self::callback(function (array $options): bool {
                        self::assertSame('Email', $options['label'] ?? null);
                        self::assertTrue($options['required'] ?? false);
                        return true;
                    }),
                ]
            )
            ->willReturn($builder);

        $type = new UserType();
        $type->buildForm($builder, ['allow_roles_edit' => false]);
    }

    public function testBuildFormDoesNotAddRolesWhenNotAllowed(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $added = [];
        $builder->method('add')->willReturnCallback(function (string $name, string $type, array $options) use (&$added, $builder) {
            $added[] = [$name, $type, $options];
            return $builder;
        });

        // If allow_roles_edit is false, no listener should be added either
        $builder->expects(self::never())
            ->method('addEventListener')
            ->with(FormEvents::PRE_SUBMIT, self::anything());

        $type = new UserType();
        $type->buildForm($builder, ['allow_roles_edit' => false]);

        $fieldNames = array_map(static fn(array $row) => $row[0], $added);
        self::assertContains('firstName', $fieldNames);
        self::assertContains('lastName', $fieldNames);
        self::assertContains('email', $fieldNames);
        self::assertNotContains('roles', $fieldNames);
    }

    public function testBuildFormAddsRolesAndListenerWhenAllowed(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);

        $added = [];
        $builder->method('add')->willReturnCallback(function (string $name, string $type, array $options) use (&$added, $builder) {
            $added[$name] = [$type, $options];
            return $builder;
        });

        $capturedListener = null;

        $builder->expects(self::once())
            ->method('addEventListener')
            ->with(
                FormEvents::PRE_SUBMIT,
                self::callback(function (callable $listener) use (&$capturedListener): bool {
                    $capturedListener = $listener;
                    return true;
                })
            );

        $type = new UserType();
        $type->buildForm($builder, ['allow_roles_edit' => true]);

        self::assertArrayHasKey('roles', $added);
        [$rolesType, $rolesOptions] = $added['roles'];

        self::assertSame(ChoiceType::class, $rolesType);
        self::assertSame('Rôle', $rolesOptions['label'] ?? null);
        self::assertSame(['Administrateur' => 'ROLE_ADMIN'], $rolesOptions['choices'] ?? null);
        self::assertTrue($rolesOptions['expanded'] ?? false);
        self::assertTrue($rolesOptions['multiple'] ?? false);
        self::assertFalse($rolesOptions['required'] ?? true);
        self::assertSame(
            'ROLE_USER est automatique. Coche "Administrateur" pour donner ROLE_ADMIN.',
            $rolesOptions['help'] ?? null
        );

        self::assertNotNull($capturedListener, 'PRE_SUBMIT listener should be registered when roles are allowed.');

        // Now test the listener normalization
        $event = $this->createMock(FormEvent::class);

        // Case 1: ROLE_ADMIN checked -> normalized to ['ROLE_ADMIN']
        $event->expects(self::once())
            ->method('getData')
            ->willReturn(['roles' => ['ROLE_ADMIN', 'ROLE_USER']]);

        $event->expects(self::once())
            ->method('setData')
            ->with(['roles' => ['ROLE_ADMIN']]);

        ($capturedListener)($event);
    }

    public function testPreSubmitListenerNormalizesToEmptyWhenNoAdmin(): void
    {
        // Capture listener by building form with allow_roles_edit=true
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturn($builder);

        $capturedListener = null;
        $builder->expects(self::once())
            ->method('addEventListener')
            ->with(
                FormEvents::PRE_SUBMIT,
                self::callback(function (callable $listener) use (&$capturedListener): bool {
                    $capturedListener = $listener;
                    return true;
                })
            );

        $type = new UserType();
        $type->buildForm($builder, ['allow_roles_edit' => true]);

        self::assertNotNull($capturedListener);

        $event = $this->createMock(FormEvent::class);

        // No ROLE_ADMIN -> normalized to []
        $event->expects(self::once())
            ->method('getData')
            ->willReturn(['roles' => ['ROLE_USER']]);

        $event->expects(self::once())
            ->method('setData')
            ->with(['roles' => []]);

        ($capturedListener)($event);
    }

    public function testPreSubmitListenerDoesNothingWhenDataNotArray(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->method('add')->willReturn($builder);

        $capturedListener = null;
        $builder->expects(self::once())
            ->method('addEventListener')
            ->with(
                FormEvents::PRE_SUBMIT,
                self::callback(function (callable $listener) use (&$capturedListener): bool {
                    $capturedListener = $listener;
                    return true;
                })
            );

        $type = new UserType();
        $type->buildForm($builder, ['allow_roles_edit' => true]);

        self::assertNotNull($capturedListener);

        $event = $this->createMock(FormEvent::class);

        // Non-array -> early return, setData must not be called
        $event->expects(self::once())
            ->method('getData')
            ->willReturn('not-an-array');

        $event->expects(self::never())
            ->method('setData');

        ($capturedListener)($event);
    }

    public function testConfigureOptionsDefaultsAndAllowedTypes(): void
    {
        $resolver = new OptionsResolver();

        $type = new UserType();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        self::assertSame(User::class, $options['data_class']);
        self::assertTrue($options['allow_roles_edit']);

        // allowed types: bool
        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $resolver->resolve(['allow_roles_edit' => 'yes']);
    }
}