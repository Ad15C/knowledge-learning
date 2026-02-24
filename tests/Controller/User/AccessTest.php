<?php

namespace App\Tests\Controller\User;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AccessTest extends WebTestCase
{
    /**
     * @dataProvider dashboardRoutesProvider
     */
    public function testDashboardRoutesRequireLogin(string $url): void
    {
        $client = static::createClient();
        $client->request('GET', $url);

        $this->assertTrue($client->getResponse()->isRedirect(), "La route $url devrait rediriger si non loggé.");
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function dashboardRoutesProvider(): array
    {
        return [
            ['/dashboard'],
            ['/dashboard/edit'],
            ['/dashboard/password'],
            ['/dashboard/purchases'],
            ['/dashboard/certifications'],
        ];
    }
}