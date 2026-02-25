<?php

namespace App\Tests\Entity;

use App\Entity\Contact;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ContactTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    public function testValidContactHasNoViolations(): void
    {
        $c = new Contact();
        $c->setFullname('Valid Name');
        $c->setEmail('valid@example.com');
        $c->setSubject('theme');
        $c->setMessage('Message suffisamment long.');

        $violations = $this->validator->validate($c);

        // sentAt est rempli au persist (subscriber), pas à la validation => aucune contrainte dessus
        $this->assertCount(0, $violations);
    }

    public function testInvalidEmailTriggersViolation(): void
    {
        $c = new Contact();
        $c->setFullname('Name');
        $c->setEmail('not-an-email');
        $c->setSubject('theme');
        $c->setMessage('Message suffisamment long.');

        $violations = $this->validator->validate($c);

        $this->assertGreaterThan(0, $violations->count());

        $messages = [];
        foreach ($violations as $v) {
            $messages[] = $v->getMessage();
        }

        $this->assertContains('Veuillez renseigner un e-mail valide.', $messages);
    }

    public function testBlankFieldsTriggerViolations(): void
    {
        $c = new Contact();
        // tout vide

        $violations = $this->validator->validate($c);

        $this->assertGreaterThan(0, $violations->count());
    }
}