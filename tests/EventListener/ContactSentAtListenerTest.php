<?php

namespace App\Tests\EventListener;

use App\Entity\Contact;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ContactSentAtListenerTest extends KernelTestCase
{
    use DoctrineSchemaTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($this->em);
    }

    private function fillRequiredFieldsIfAny(Contact $contact): void
    {
        // Adapte automatiquement si ces setters existent chez toi
        if (method_exists($contact, 'setFirstname')) {
            $contact->setFirstname('Test');
        }
        if (method_exists($contact, 'setLastname')) {
            $contact->setLastname('User');
        }
        if (method_exists($contact, 'setFullName')) {
            $contact->setFullName('Test User');
        }
        if (method_exists($contact, 'setEmail')) {
            $contact->setEmail('test@example.com');
        }
        if (method_exists($contact, 'setSubject')) {
            $contact->setSubject('Test subject');
        }
        if (method_exists($contact, 'setMessage')) {
            $contact->setMessage('Test message');
        }
        if (method_exists($contact, 'setContent')) {
            $contact->setContent('Test content');
        }
        if (method_exists($contact, 'setPhone')) {
            $contact->setPhone('0000000000');
        }
    }

    public function testSentAtIsAutomaticallySetOnPersist(): void
    {
        $contact = new Contact();
        $this->fillRequiredFieldsIfAny($contact);

        $this->assertNull($contact->getSentAt());

        $this->em->persist($contact);
        $this->em->flush();

        $this->assertNotNull($contact->getSentAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $contact->getSentAt());
    }

    public function testSentAtIsNotOverwrittenIfAlreadySet(): void
    {
        $initialDate = new \DateTimeImmutable('-1 day');

        $contact = new Contact();
        $this->fillRequiredFieldsIfAny($contact);
        $contact->setSentAt($initialDate);

        $this->em->persist($contact);
        $this->em->flush();

        $this->assertEquals($initialDate, $contact->getSentAt());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}