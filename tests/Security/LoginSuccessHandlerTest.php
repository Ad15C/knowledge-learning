<?php

namespace App\Tests\Security;

use App\Security\LoginSuccessHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class LoginSuccessHandlerTest extends TestCase
{
    public function testAdminIsRedirectedToAdminDashboard(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $adminUser = $this->createMock(UserInterface::class);

        $adminUser
            ->method('getRoles')
            ->willReturn(['ROLE_ADMIN']);

        $token
            ->method('getUser')
            ->willReturn($adminUser);

        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('admin_dashboard')
            ->willReturn('/admin');

        $handler = new LoginSuccessHandler($urlGenerator);

        $response = $handler->onAuthenticationSuccess(new Request(), $token);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin', $response->getTargetUrl());
    }

    public function testUserIsRedirectedToUserDashboard(): void
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $token = $this->createMock(TokenInterface::class);
        $normalUser = $this->createMock(UserInterface::class);

        $normalUser
            ->method('getRoles')
            ->willReturn(['ROLE_USER']);

        $token
            ->method('getUser')
            ->willReturn($normalUser);

        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('user_dashboard')
            ->willReturn('/dashboard');

        $handler = new LoginSuccessHandler($urlGenerator);

        $response = $handler->onAuthenticationSuccess(new Request(), $token);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/dashboard', $response->getTargetUrl());
    }
}