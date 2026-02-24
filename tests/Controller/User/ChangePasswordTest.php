<?php

namespace App\Tests\Controller\User;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordTest extends AbstractUserWebTestCase
{
    public function testChangePasswordPageLoads(): void
    {
        $client = $this->client;
        $client->loginUser($this->getFixtureUser());

        $crawler = $client->request('GET', '/dashboard/password');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Changer mon mot de passe');
        $this->assertSelectorExists('form');

        // 2 champs password (RepeatedType)
        $this->assertGreaterThanOrEqual(2, $crawler->filter('input[type="password"]')->count());

        // Bouton HTML générique du container
        $this->assertSelectorExists('button#change-password-submit[type="submit"]');
        $this->assertSelectorTextContains('#change-password-submit', 'Modifier le mot de passe');
    }

    public function testChangePasswordSubmitUpdatesHashAndRedirects(): void
    {
        $client = $this->client;

        /** @var \App\Entity\User $user */
        $user = $this->getFixtureUser();
        $client->loginUser($user);

        $oldHash = $this->em->getRepository(\App\Entity\User::class)->find($user->getId())->getPassword();

        $crawler = $client->request('GET', '/dashboard/password');
        $this->assertResponseIsSuccessful();

        $passwordInputs = $crawler->filter('input[type="password"]');
        $this->assertGreaterThanOrEqual(2, $passwordInputs->count(), 'On attend 2 champs password.');

        $firstName  = (string) $passwordInputs->eq(0)->attr('name');
        $secondName = (string) $passwordInputs->eq(1)->attr('name');

        $this->assertNotEmpty($firstName);
        $this->assertNotEmpty($secondName);

        // Vérifie que le CSRF est bien présent dans le HTML
        $this->assertGreaterThan(
            0,
            $crawler->filter('input[type="hidden"][name="change_password[_token]"]')->count(),
            'Le champ CSRF change_password[_token] doit être présent (form_rest).'
        );

        $newPass = 'NewPassword123!';

        $form = $crawler->filter('button#change-password-submit')->form();
        $form[$firstName]  = $newPass;
        $form[$secondName] = $newPass;

        $client->submit($form);

        // Si pas de redirection -> on sort un debug ACTIONNABLE
        if (!$client->getResponse()->isRedirect()) {
            $status = $client->getResponse()->getStatusCode();
            $html = $client->getResponse()->getContent() ?? '';

            $request = $client->getRequest();
            $method = $request ? $request->getMethod() : 'UNKNOWN';
            $postKeys = $request ? array_keys($request->request->all()) : [];

            $crawlerAfter = $client->getCrawler();
            $errors = [];

            if ($crawlerAfter) {
                // tes erreurs de champ
                if ($crawlerAfter->filter('.form-error')->count() > 0) {
                    $errors = array_merge($errors, $crawlerAfter->filter('.form-error')->each(fn($n) => trim($n->text())));
                }
                // erreurs globales (form_errors(form)) rend souvent <ul><li>...</li></ul>
                if ($crawlerAfter->filter('form ul li')->count() > 0) {
                    $errors = array_merge($errors, $crawlerAfter->filter('form ul li')->each(fn($n) => trim($n->text())));
                }
            }

            $errors = array_values(array_unique(array_filter($errors)));

            $this->fail(
                "Pas de redirection après submit.\n" .
                "HTTP status: {$status}\n" .
                "Request method: {$method}\n" .
                "POST keys: " . implode(', ', $postKeys) . "\n" .
                "Input names détectés: first={$firstName} / second={$secondName}\n" .
                (count($errors) ? "Erreurs détectées: " . implode(' | ', $errors) . "\n" : "Aucune erreur détectée\n") .
                "Extrait HTML (2000 chars):\n" . substr($html, 0, 2000)
            );
        }

        $client->followRedirect();

        $this->assertSelectorExists('.flash');
        $this->assertSelectorTextContains('.flash', 'Mot de passe mis à jour');

        $this->em->clear();
        $reloaded = $this->em->getRepository(\App\Entity\User::class)->find($user->getId());

        $this->assertNotNull($reloaded);
        $this->assertNotSame($oldHash, $reloaded->getPassword());

        /** @var \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($reloaded, $newPass));
    }
}