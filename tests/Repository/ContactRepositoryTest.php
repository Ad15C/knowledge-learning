<?php

namespace App\Tests\Repository;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ContactRepositoryTest extends KernelTestCase
{
    public function testRepositoryCanPersistAndFind(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        // Nettoyage
        $em->createQuery('DELETE FROM App\Entity\Contact c')->execute();

        $c = new Contact();
        $c->setFullname('Repo User');
        $c->setEmail('repo.user@example.com');
        $c->setSubject('other');
        $c->setMessage('Message repository test');

        $em->persist($c);
        $em->flush();
        $em->clear();

        $found = $em->getRepository(Contact::class)->findOneBy(['email' => 'repo.user@example.com']);
        $this->assertNotNull($found);
        $this->assertSame('Repo User', $found->getFullname());
        $this->assertInstanceOf(\DateTimeImmutable::class, $found->getSentAt());
    }
}