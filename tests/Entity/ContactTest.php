<?php

namespace App\Tests\Entity;

use App\Entity\Contact;
use PHPUnit\Framework\TestCase;

class ContactTest extends TestCase
{
    public function testDefaultsOnConstruct(): void
    {
        $contact = new Contact();

        $this->assertInstanceOf(\DateTime::class, $contact->getSentAt());
        $this->assertFalse($contact->isHandled());
    }

    public function testSettersAndGetters(): void
    {
        $contact = new Contact();
        $sentAt = new \DateTime('2026-02-24 12:00:00');

        $contact->setFullname('John Doe')
            ->setEmail('john@example.com')
            ->setSubject('Sujet')
            ->setMessage('Message')
            ->setSentAt($sentAt)
            ->setHandled(true);

        $this->assertSame('John Doe', $contact->getFullname());
        $this->assertSame('john@example.com', $contact->getEmail());
        $this->assertSame('Sujet', $contact->getSubject());
        $this->assertSame('Message', $contact->getMessage());
        $this->assertSame($sentAt, $contact->getSentAt());
        $this->assertTrue($contact->isHandled());
    }
}