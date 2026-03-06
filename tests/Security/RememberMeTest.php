<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RememberMeTest extends WebTestCase
{
    public function testRememberMeCookieIsCreated(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Se connecter')->form();

        $form['_username'] = 'user@test.com';
        $form['_password'] = 'password';
        $form['_remember_me'] = 'on';

        $client->submit($form);

        $cookies = $client->getResponse()->headers->getCookies();

        $found = false;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'REMEMBERME' || $cookie->getName() === 'remember_me') {
                $found = true;
            }
        }

        $this->assertTrue($found, 'Le cookie remember-me doit être créé.');
    }
}