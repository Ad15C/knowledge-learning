<?php

namespace App\Tests\Form;

use App\Entity\Contact;
use App\Form\ContactFormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class ContactFormTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        // Permet de faire fonctionner $form->isValid() avec les Assert de l'Entity
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testFormHasExpectedFields(): void
    {
        $form = $this->factory->create(ContactFormType::class);

        $this->assertTrue($form->has('fullname'));
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('subject'));
        $this->assertTrue($form->has('message'));
    }

    public function testSubmitValidDataMapsToEntityAndIsValid(): void
    {
        $contact = new Contact();
        $form = $this->factory->create(ContactFormType::class, $contact);

        $form->submit([
            'fullname' => 'Marie Curie',
            'email' => 'marie@example.com',
            'subject' => 'cursus',
            'message' => 'Ceci est un message assez long.',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());

        $this->assertSame('Marie Curie', $contact->getFullname());
        $this->assertSame('marie@example.com', $contact->getEmail());
        $this->assertSame('cursus', $contact->getSubject());
        $this->assertSame('Ceci est un message assez long.', $contact->getMessage());
    }

    public function testInvalidEmailMakesFormInvalid(): void
    {
        $form = $this->factory->create(ContactFormType::class, new Contact());

        $form->submit([
            'fullname' => 'Marie',
            'email' => 'not-an-email',
            'subject' => 'theme',
            'message' => 'Ceci est un message assez long.',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('email')->getErrors(true)->count());
    }

    public function testTooShortMessageMakesFormInvalid(): void
    {
        $form = $this->factory->create(ContactFormType::class, new Contact());

        $form->submit([
            'fullname' => 'Marie',
            'email' => 'marie@example.com',
            'subject' => 'theme',
            'message' => 'Court', // < 10 caractères
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());
        $this->assertGreaterThan(0, $form->get('message')->getErrors(true)->count());
    }

    public function testBlankFieldsMakeFormInvalid(): void
    {
        $form = $this->factory->create(ContactFormType::class, new Contact());

        $form->submit([
            'fullname' => '',
            'email' => '',
            'subject' => '',
            'message' => '',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $this->assertGreaterThan(0, $form->get('fullname')->getErrors(true)->count());
        $this->assertGreaterThan(0, $form->get('email')->getErrors(true)->count());
        $this->assertGreaterThan(0, $form->get('subject')->getErrors(true)->count());
        $this->assertGreaterThan(0, $form->get('message')->getErrors(true)->count());
    }

    public function testSubjectChoicesAndPlaceholderAreCorrect(): void
    {
        $form = $this->factory->create(ContactFormType::class);
        $subjectConfig = $form->get('subject')->getConfig();

        // Placeholder
        $this->assertSame('Choisissez un sujet', $subjectConfig->getOption('placeholder'));

        // Choices (libellé => valeur)
        $choices = $subjectConfig->getOption('choices');

        $this->assertSame('theme', $choices['Question sur un thème']);
        $this->assertSame('cursus', $choices['Question sur un cursus']);
        $this->assertSame('lesson', $choices['Question sur une leçon']);
        $this->assertSame('payment', $choices['Question sur le paiement']);
        $this->assertSame('validation', $choices['Question sur la validation du cours']);
        $this->assertSame('certification', $choices['Question sur la certification']);
        $this->assertSame('registration', $choices['Question sur l’inscription']);
        $this->assertSame('login', $choices['Question sur la connexion']);
        $this->assertSame('other', $choices['Autre question']);
    }
}