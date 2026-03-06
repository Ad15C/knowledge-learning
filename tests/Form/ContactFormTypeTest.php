<?php

namespace App\Tests\Form;

use App\Entity\Contact;
use App\Form\ContactFormType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class ContactFormTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = static::getContainer()->get('form.factory');
    }

    private function createForm(?Contact $contact = null): FormInterface
    {
        return $this->formFactory->create(ContactFormType::class, $contact, [
            'csrf_protection' => false,
        ]);
    }

    private function getAllErrorMessages(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors(true, true) as $error) {
            $messages[] = $error->getMessage();
        }

        return $messages;
    }

    private function assertFormErrorsContain(FormInterface $form, string $expectedPart): void
    {
        $messages = $this->getAllErrorMessages($form);

        foreach ($messages as $msg) {
            if (str_contains($msg, $expectedPart)) {
                $this->assertTrue(true);
                return;
            }
        }

        $this->fail(sprintf(
            "Expected form errors to contain '%s'. Got: %s",
            $expectedPart,
            implode(' | ', $messages)
        ));
    }

    public function testFormHasExpectedFields(): void
    {
        $form = $this->createForm();

        $this->assertTrue($form->has('fullname'));
        $this->assertTrue($form->has('email'));
        $this->assertTrue($form->has('subject'));
        $this->assertTrue($form->has('message'));
    }

    public function testSubmitValidDataMapsToEntityAndIsValid(): void
    {
        $contact = new Contact();
        $form = $this->createForm($contact);

        $form->submit([
            'fullname' => 'Marie Curie',
            'email' => 'marie@example.com',
            'subject' => 'cursus',
            'message' => 'Ceci est un message assez long.',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid(), implode(' | ', $this->getAllErrorMessages($form)));

        $this->assertSame('Marie Curie', $contact->getFullname());
        $this->assertSame('marie@example.com', $contact->getEmail());
        $this->assertSame('cursus', $contact->getSubject());
        $this->assertSame('Ceci est un message assez long.', $contact->getMessage());
    }

    public function testInvalidEmailMakesFormInvalid(): void
    {
        $form = $this->createForm(new Contact());

        $form->submit([
            'fullname' => 'Marie',
            'email' => 'not-an-email',
            'subject' => 'theme',
            'message' => 'Ceci est un message assez long.',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $this->assertFormErrorsContain($form, 'Veuillez renseigner un e-mail valide.');
    }

    public function testTooShortMessageMakesFormInvalid(): void
    {
        $form = $this->createForm(new Contact());

        $form->submit([
            'fullname' => 'Marie',
            'email' => 'marie@example.com',
            'subject' => 'theme',
            'message' => 'Court',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $this->assertFormErrorsContain($form, 'Le message doit faire au moins');
    }

    public function testBlankFieldsMakeFormInvalid(): void
    {
        $form = $this->createForm(new Contact());

        $form->submit([
            'fullname' => '',
            'email' => '',
            'message' => '',
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $this->assertFormErrorsContain($form, 'Veuillez renseigner votre nom.');
        $this->assertFormErrorsContain($form, 'Veuillez renseigner votre e-mail.');
        $this->assertFormErrorsContain($form, 'Veuillez choisir un sujet.');
        $this->assertFormErrorsContain($form, 'Veuillez écrire un message.');
    }

    public function testSubjectChoicesAndPlaceholderAreCorrect(): void
    {
        $form = $this->createForm();
        $subjectConfig = $form->get('subject')->getConfig();

        $this->assertSame('Choisissez un sujet', $subjectConfig->getOption('placeholder'));

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