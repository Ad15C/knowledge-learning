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

    private function getViolationMessages(Contact $c): array
    {
        $violations = $this->validator->validate($c);

        $messages = [];
        foreach ($violations as $v) {
            $messages[] = $v->getMessage();
        }

        return $messages;
    }

    public function testValidContactHasNoViolations(): void
    {
        $c = (new Contact())
            ->setFullname('Valid Name')
            ->setEmail('valid@example.com')
            ->setSubject('theme')
            ->setMessage('Message suffisamment long.');

        $violations = $this->validator->validate($c);

        self::assertCount(0, $violations);
    }

    public function testInvalidEmailTriggersViolation(): void
    {
        $c = (new Contact())
            ->setFullname('Name')
            ->setEmail('not-an-email')
            ->setSubject('theme')
            ->setMessage('Message suffisamment long.');

        $messages = $this->getViolationMessages($c);

        self::assertContains('Veuillez renseigner un e-mail valide.', $messages);
    }

    public function testBlankFieldsTriggerExpectedViolations(): void
    {
        $c = new Contact();

        $messages = $this->getViolationMessages($c);

        self::assertContains('Veuillez renseigner votre nom.', $messages);
        self::assertContains('Veuillez renseigner votre e-mail.', $messages);
        self::assertContains('Veuillez choisir un sujet.', $messages);
        self::assertContains('Veuillez écrire un message.', $messages);
    }

    public function testMessageTooShortTriggersViolation(): void
    {
        $c = (new Contact())
            ->setFullname('Valid Name')
            ->setEmail('valid@example.com')
            ->setSubject('theme')
            ->setMessage('short'); // 5 caractères -> forcément < 10

        $violations = $this->validator->validate($c);

        // On attend AU MOINS une violation sur le champ message
        $found = false;
        foreach ($violations as $v) {
            if ($v->getPropertyPath() === 'message') {
                $found = true;
                break;
            }
        }

        $this->assertTrue(
            $found,
            'Expected a violation on "message". Got: ' . implode(' | ', array_map(
                fn($v) => $v->getPropertyPath() . ': ' . $v->getMessage(),
                iterator_to_array($violations)
            ))
        );
    }

    public function testMarkReadAndUnreadAndStatus(): void
    {
        $c = new Contact();

        self::assertFalse($c->isRead());
        self::assertSame('unread', $c->getStatus());

        $c->markRead();
        self::assertTrue($c->isRead());
        self::assertNotNull($c->getReadAt());
        self::assertSame('read', $c->getStatus());

        $c->markUnread();
        self::assertFalse($c->isRead());
        self::assertNull($c->getReadAt());
        self::assertSame('unread', $c->getStatus());
    }

    public function testHandledSetsHandledAtAndStatus(): void
    {
        $c = new Contact();

        self::assertFalse($c->isHandled());
        self::assertNull($c->getHandledAt());
        self::assertSame('unread', $c->getStatus());

        $c->setHandled(true);
        self::assertTrue($c->isHandled());
        self::assertNotNull($c->getHandledAt());
        self::assertSame('handled', $c->getStatus());

        $c->setHandled(false);
        self::assertFalse($c->isHandled());
        self::assertNull($c->getHandledAt());
        self::assertSame('unread', $c->getStatus());
    }

    public function testSubjectLabelMapping(): void
    {
        $c = new Contact();

        $c->setSubject('theme');
        self::assertSame('Question sur un thème', $c->getSubjectLabel());

        $c->setSubject('payment');
        self::assertSame('Question sur le paiement', $c->getSubjectLabel());

        $c->setSubject('other');
        self::assertSame('Autre question', $c->getSubjectLabel());

        $c->setSubject('unknown_subject');
        self::assertSame('unknown_subject', $c->getSubjectLabel());
    }
}