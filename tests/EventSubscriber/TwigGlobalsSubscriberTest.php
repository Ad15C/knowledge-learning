<?php

namespace App\Tests\EventSubscriber;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TwigGlobalsSubscriberTest extends WebTestCase
{
    public function testPagesUsingBaseDoNotCrashAndShowCartMenu(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login'); // page qui étend base.html.twig

        $this->assertResponseIsSuccessful();

        // Vérifie que le menu contient "Panier"
        $this->assertSelectorTextContains('nav', 'Panier');
    }
}