<?php

namespace App\Tests\Repository;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ContactRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ContactRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repo = $this->em->getRepository(Contact::class);

        // Nettoyage
        $this->em->createQuery('DELETE FROM App\Entity\Contact c')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em, $this->repo);
    }

    private function makeContact(
        string $fullname,
        string $email,
        string $subject,
        string $message,
        \DateTimeImmutable $sentAt,
        bool $handled = false,
        ?\DateTimeImmutable $readAt = null
    ): Contact {
        $c = new Contact();
        $c->setFullname($fullname)
            ->setEmail($email)
            ->setSubject($subject)
            ->setMessage($message)
            ->setSentAt($sentAt)
            ->setHandled($handled);

        if ($readAt !== null) {
            // pas de setter readAt => on utilise markRead() puis on "force" via setHandledAt ? non.
            // markRead() met readAt à maintenant, donc on ne l’utilise pas pour fixer une date précise.
            // Ici on a besoin juste de "read" vs "unread", donc markRead suffit :
            $c->markRead();
        }

        $this->em->persist($c);

        return $c;
    }

    public function testRepositoryCanPersistAndFind(): void
    {
        $sentAt = new \DateTimeImmutable('2026-03-01 10:00:00');

        $c = new Contact();
        $c->setFullname('Repo User')
            ->setEmail('repo.user@example.com')
            ->setSubject('other')
            ->setMessage('Message repository test')
            ->setSentAt($sentAt);

        $this->em->persist($c);
        $this->em->flush();
        $this->em->clear();

        /** @var Contact|null $found */
        $found = $this->repo->findOneBy(['email' => 'repo.user@example.com']);

        self::assertNotNull($found);
        self::assertSame('Repo User', $found->getFullname());
        self::assertInstanceOf(\DateTimeImmutable::class, $found->getSentAt());
    }

    public function testFindUnreadReturnsOnlyUnreadAndNotHandledOrderedBySentAtDesc(): void
    {
        // Unread + not handled => doit sortir
        $this->makeContact(
            'Unread 1',
            'u1@example.com',
            'theme',
            'Hello unread 1',
            new \DateTimeImmutable('2026-03-01 10:00:00'),
            false,
            null
        );

        // Unread + not handled => doit sortir (plus récent)
        $this->makeContact(
            'Unread 2',
            'u2@example.com',
            'payment',
            'Hello unread 2',
            new \DateTimeImmutable('2026-03-01 11:00:00'),
            false,
            null
        );

        // Read + not handled => ne doit PAS sortir
        $this->makeContact(
            'Read 1',
            'r1@example.com',
            'payment',
            'Hello read',
            new \DateTimeImmutable('2026-03-01 12:00:00'),
            false,
            new \DateTimeImmutable('2026-03-01 12:05:00')
        );

        // Handled (même unread) => ne doit PAS sortir
        $this->makeContact(
            'Handled 1',
            'h1@example.com',
            'other',
            'Hello handled',
            new \DateTimeImmutable('2026-03-01 13:00:00'),
            true,
            null
        );

        $this->em->flush();
        $this->em->clear();

        $results = $this->repo->findUnread();

        self::assertCount(2, $results);
        // tri DESC sentAt : Unread 2 (11h) puis Unread 1 (10h)
        self::assertSame('u2@example.com', $results[0]->getEmail());
        self::assertSame('u1@example.com', $results[1]->getEmail());
    }

    public function testFindByFiltersSubject(): void
    {
        $this->makeContact('A', 'a@example.com', 'payment', 'msg a', new \DateTimeImmutable('2026-03-01 10:00:00'));
        $this->makeContact('B', 'b@example.com', 'theme', 'msg b', new \DateTimeImmutable('2026-03-01 11:00:00'));
        $this->em->flush();
        $this->em->clear();

        $results = $this->repo->findByFilters(['subject' => 'payment']);

        self::assertCount(1, $results);
        self::assertSame('a@example.com', $results[0]->getEmail());
    }

    public function testFindByFiltersStatusUnreadReadHandled(): void
    {
        // unread
        $this->makeContact('Unread', 'unread@example.com', 'other', 'msg', new \DateTimeImmutable('2026-03-01 10:00:00'));

        // read (markRead)
        $this->makeContact('Read', 'read@example.com', 'other', 'msg', new \DateTimeImmutable('2026-03-01 11:00:00'), false, new \DateTimeImmutable());

        // handled
        $this->makeContact('Handled', 'handled@example.com', 'other', 'msg', new \DateTimeImmutable('2026-03-01 12:00:00'), true);

        $this->em->flush();
        $this->em->clear();

        $unread = $this->repo->findByFilters(['status' => 'unread']);
        self::assertCount(1, $unread);
        self::assertSame('unread@example.com', $unread[0]->getEmail());

        $read = $this->repo->findByFilters(['status' => 'read']);
        self::assertCount(1, $read);
        self::assertSame('read@example.com', $read[0]->getEmail());

        $handled = $this->repo->findByFilters(['status' => 'handled']);
        self::assertCount(1, $handled);
        self::assertSame('handled@example.com', $handled[0]->getEmail());
    }

    public function testFindByFiltersQuerySearchInFullnameEmailOrMessage(): void
    {
        $this->makeContact(
            'Marie Curie',
            'marie@example.com',
            'theme',
            'Message scientifique',
            new \DateTimeImmutable('2026-03-01 10:00:00')
        );

        $this->makeContact(
            'Albert',
            'albert@example.com',
            'theme',
            'Relativité',
            new \DateTimeImmutable('2026-03-01 11:00:00')
        );

        $this->em->flush();
        $this->em->clear();

        // match fullname
        $r1 = $this->repo->findByFilters(['q' => 'Curie']);
        self::assertCount(1, $r1);
        self::assertSame('marie@example.com', $r1[0]->getEmail());

        // match email
        $r2 = $this->repo->findByFilters(['q' => 'albert@']);
        self::assertCount(1, $r2);
        self::assertSame('albert@example.com', $r2[0]->getEmail());

        // match message
        $r3 = $this->repo->findByFilters(['q' => 'Relativité']);
        self::assertCount(1, $r3);
        self::assertSame('albert@example.com', $r3[0]->getEmail());
    }

    public function testFindByFiltersCombinesSubjectStatusAndQuery(): void
    {
        // cible: subject=payment + status=unread + q contains "refund"
        $this->makeContact(
            'Pay Unread',
            'pay1@example.com',
            'payment',
            'Need refund please',
            new \DateTimeImmutable('2026-03-01 10:00:00'),
            false,
            null
        );

        // même subject mais read => exclu
        $this->makeContact(
            'Pay Read',
            'pay2@example.com',
            'payment',
            'Need refund please',
            new \DateTimeImmutable('2026-03-01 11:00:00'),
            false,
            new \DateTimeImmutable()
        );

        // unread mais autre subject => exclu
        $this->makeContact(
            'Other Unread',
            'other@example.com',
            'other',
            'Need refund please',
            new \DateTimeImmutable('2026-03-01 12:00:00'),
            false,
            null
        );

        // unread + payment mais pas le mot => exclu
        $this->makeContact(
            'Pay Unread NoWord',
            'pay3@example.com',
            'payment',
            'Hello',
            new \DateTimeImmutable('2026-03-01 13:00:00'),
            false,
            null
        );

        $this->em->flush();
        $this->em->clear();

        $results = $this->repo->findByFilters([
            'subject' => 'payment',
            'status' => 'unread',
            'q' => 'refund',
        ]);

        self::assertCount(1, $results);
        self::assertSame('pay1@example.com', $results[0]->getEmail());
    }
}