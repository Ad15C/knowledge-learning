<?php

namespace App\Tests\Controller;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ContactControllerTest extends WebTestCase
{
    public function testGetContactPageDisplaysForm(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact/');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="contact_form[fullname]"]');
        $this->assertSelectorExists('input[name="contact_form[email]"]');
        $this->assertSelectorExists('select[name="contact_form[subject]"]');
        $this->assertSelectorExists('textarea[name="contact_form[message]"]');
    }

    public function testPostValidContactPersistsAndRedirects(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact/');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();
        $form['contact_form[fullname]'] = 'Jean Test';
        $form['contact_form[email]'] = 'jean.test@example.com';
        $form['contact_form[subject]'] = 'theme';
        $form['contact_form[message]'] = 'Bonjour, ceci est un message de test.';

        $client->submit($form);

        $this->assertResponseRedirects('/contact/');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $saved = $em->getRepository(Contact::class)->findOneBy(['email' => 'jean.test@example.com']);
        $this->assertNotNull($saved);
        $this->assertInstanceOf(\DateTimeImmutable::class, $saved->getSentAt());
    }

    public function testContactPageRendersTemplateHtml(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact/');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Nous contacter');
        $this->assertSelectorExists('input[placeholder="Votre nom"]');
        $this->assertSelectorExists('input[placeholder="Votre e-mail"]');
        $this->assertSelectorExists('textarea[placeholder="Votre message"]');
        $this->assertSelectorExists('button.btn-submit');
    }

    public function testContactTwigRendersBaseElements(): void
    {
        $client = static::createClient();
        $client->request('GET', '/contact/');

        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Nous contacter');
        $this->assertSelectorExists('.contact-page p');
        $this->assertSelectorTextContains('.contact-page p', "n’hésitez pas à nous écrire");

        $this->assertSelectorExists('input[placeholder="Votre nom"]');
        $this->assertSelectorExists('input[placeholder="Votre e-mail"]');
        $this->assertSelectorExists('textarea[placeholder="Votre message"]');
        $this->assertSelectorExists('button.btn-submit');
    }

    public function testContactTwigDisplaysValidationErrorsOnEmptySubmit(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact/');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();
        $client->submit($form);

        $this->assertResponseIsSuccessful();

        $this->assertStringContainsString('Veuillez renseigner votre nom.', $client->getResponse()->getContent());
        $this->assertStringContainsString('Veuillez renseigner votre e-mail.', $client->getResponse()->getContent());
        $this->assertStringContainsString('Veuillez choisir un sujet.', $client->getResponse()->getContent());
        $this->assertStringContainsString('Veuillez écrire un message.', $client->getResponse()->getContent());
    }

    public function testContactTwigDisplaysFlashAfterSuccessSubmit(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact/');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();
        $form['contact_form[fullname]'] = 'Twig Flash';
        $form['contact_form[email]'] = 'twig.flash@example.com';
        $form['contact_form[subject]'] = 'theme';
        $form['contact_form[message]'] = 'Message assez long pour passer la validation.';

        $client->submit($form);

        $this->assertResponseRedirects('/contact/');
        $client->followRedirect();

        // Ici ça dépend où tu affiches les flashs (base.html.twig ou template)
        // Si tu as <div class="flash flash-success">...</div>
        $this->assertSelectorExists('.flash, .flash-success, .flash-warning');
    }
}