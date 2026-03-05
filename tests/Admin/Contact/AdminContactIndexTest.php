<?php

namespace App\Tests\Admin\Contact;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminContactIndexTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        // Force HTTPS + follow redirects (ton app force https via 301)
        $this->client = static::createClient([], [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
        ]);
        $this->client->followRedirects(true);

        /** @var EntityManagerInterface $em */
        $em = $this->client->getContainer()->get('doctrine')->getManager();
        $this->em = $em;

        $this->purgeContacts();
        $this->purgeUsers();
    }

    public function testIndexWithoutFiltersSortedBySentAtDesc(): void
    {
        $this->createAdminAndLogin();

        $this->createContact([
            'fullname' => 'Old One',
            'email' => 'old@example.com',
            'subject' => 'payment',
            'message' => 'Message old old old',
            'sentAt' => new \DateTimeImmutable('2024-01-01 10:00:00'),
            'readAt' => null,
            'handled' => false,
        ]);

        $this->createContact([
            'fullname' => 'Mid One',
            'email' => 'mid@example.com',
            'subject' => 'login',
            'message' => 'Message mid mid mid',
            'sentAt' => new \DateTimeImmutable('2024-01-02 10:00:00'),
            'readAt' => new \DateTimeImmutable('2024-01-02 12:00:00'),
            'handled' => false,
        ]);

        $this->createContact([
            'fullname' => 'New One',
            'email' => 'new@example.com',
            'subject' => 'theme',
            'message' => 'Message new new new',
            'sentAt' => new \DateTimeImmutable('2024-01-03 10:00:00'),
            'readAt' => null,
            'handled' => true,
        ]);

        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/contact/');
        $this->assertResponseIsSuccessful();

        $rows = $crawler->filter('table.admin-table tbody tr');
        $this->assertGreaterThanOrEqual(3, $rows->count());

        $firstEmail = trim($rows->eq(0)->filter('td')->eq(2)->text());
        $secondEmail = trim($rows->eq(1)->filter('td')->eq(2)->text());
        $thirdEmail = trim($rows->eq(2)->filter('td')->eq(2)->text());

        $this->assertSame('new@example.com', $firstEmail);
        $this->assertSame('mid@example.com', $secondEmail);
        $this->assertSame('old@example.com', $thirdEmail);
    }

    public function testFilterSubjectExact(): void
    {
        $this->createAdminAndLogin();

        $this->createContact([
            'fullname' => 'Alice Payment',
            'email' => 'alice.payment@example.com',
            'subject' => 'payment',
            'message' => 'Question paiement blah blah',
            'sentAt' => new \DateTimeImmutable('2024-02-01 10:00:00'),
        ]);

        $this->createContact([
            'fullname' => 'Bob Login',
            'email' => 'bob.login@example.com',
            'subject' => 'login',
            'message' => 'Question login blah blah',
            'sentAt' => new \DateTimeImmutable('2024-02-02 10:00:00'),
        ]);

        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/contact/?subject=payment');
        $this->assertResponseIsSuccessful();

        $rows = $crawler->filter('table.admin-table tbody tr');
        $this->assertSame(1, $rows->count());

        $subjectCell = trim($rows->eq(0)->filter('td')->eq(3)->text());
        $this->assertStringContainsString('paiement', mb_strtolower($subjectCell));
    }

    public function testFilterStatusUnreadReadHandled(): void
    {
        $this->createAdminAndLogin();

        $this->createContact([
            'fullname' => 'Unread Person',
            'email' => 'unread@example.com',
            'subject' => 'other',
            'message' => 'Unread message content',
            'sentAt' => new \DateTimeImmutable('2024-03-01 10:00:00'),
            'readAt' => null,
            'handled' => false,
        ]);

        $this->createContact([
            'fullname' => 'Read Person',
            'email' => 'read@example.com',
            'subject' => 'other',
            'message' => 'Read message content',
            'sentAt' => new \DateTimeImmutable('2024-03-02 10:00:00'),
            'readAt' => new \DateTimeImmutable('2024-03-02 11:00:00'),
            'handled' => false,
        ]);

        $this->createContact([
            'fullname' => 'Handled Person',
            'email' => 'handled@example.com',
            'subject' => 'other',
            'message' => 'Handled message content',
            'sentAt' => new \DateTimeImmutable('2024-03-03 10:00:00'),
            'readAt' => null,
            'handled' => true,
        ]);

        $this->em->flush();

        // unread
        $crawler = $this->client->request('GET', '/admin/contact/?status=unread');
        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('table.admin-table tbody tr');
        $this->assertSame(1, $rows->count());
        $this->assertSame('unread@example.com', trim($rows->eq(0)->filter('td')->eq(2)->text()));

        // read
        $crawler = $this->client->request('GET', '/admin/contact/?status=read');
        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('table.admin-table tbody tr');
        $this->assertSame(1, $rows->count());
        $this->assertSame('read@example.com', trim($rows->eq(0)->filter('td')->eq(2)->text()));

        // handled
        $crawler = $this->client->request('GET', '/admin/contact/?status=handled');
        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('table.admin-table tbody tr');
        $this->assertSame(1, $rows->count());
        $this->assertSame('handled@example.com', trim($rows->eq(0)->filter('td')->eq(2)->text()));
    }

    public function testSearchQMatchesFullnameEmailMessageLike(): void
    {
        $this->createAdminAndLogin();

        $this->createContact([
            'fullname' => 'Jean Dupont',
            'email' => 'jean.dupont@example.com',
            'subject' => 'theme',
            'message' => 'Bonjour, je voudrais des infos sur un thème précis. TOKEN-UNIQUE-123',
            'sentAt' => new \DateTimeImmutable('2024-04-01 10:00:00'),
        ]);

        // IMPORTANT: ne mets PAS "Dupont" ici, sinon q=Dupont renvoie 2 résultats
        $this->createContact([
            'fullname' => 'Marie Curie',
            'email' => 'marie@example.com',
            'subject' => 'theme',
            'message' => 'Rien à voir avec ce message.',
            'sentAt' => new \DateTimeImmutable('2024-04-02 10:00:00'),
        ]);

        $this->em->flush();

        // fullname LIKE
        $crawler = $this->client->request('GET', '/admin/contact/?q=Dupont');
        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('table.admin-table tbody tr');
        $this->assertSame(1, $rows->count());
        $this->assertSame('jean.dupont@example.com', trim($rows->eq(0)->filter('td')->eq(2)->text()));

        // email LIKE
        $crawler = $this->client->request('GET', '/admin/contact/?q=marie@');
        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('table.admin-table tbody tr');
        $this->assertSame(1, $rows->count());
        $this->assertSame('marie@example.com', trim($rows->eq(0)->filter('td')->eq(2)->text()));

        // message LIKE (token unique => 1 seul résultat garanti)
        $crawler = $this->client->request('GET', '/admin/contact/?q=TOKEN-UNIQUE-123');
        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('table.admin-table tbody tr');
        $this->assertSame(1, $rows->count());
        $this->assertSame('jean.dupont@example.com', trim($rows->eq(0)->filter('td')->eq(2)->text()));
    }

    public function testEmailFilterConcatenatesIntoQ(): void
    {
        $this->createAdminAndLogin();

        $crawler = $this->client->request('GET', '/admin/contact/?q=Alice&email=alice@example.com');
        $this->assertResponseIsSuccessful();

        $qValue = $crawler->filter('input[name="q"]')->attr('value');
        $this->assertSame('Alice alice@example.com', $qValue);
    }

    public function testEdgeInvalidSubjectNoError(): void
    {
        $this->createAdminAndLogin();

        $this->createContact([
            'fullname' => 'Someone',
            'email' => 'someone@example.com',
            'subject' => 'payment',
            'message' => 'Hello hello hello',
            'sentAt' => new \DateTimeImmutable('2024-05-01 10:00:00'),
        ]);
        $this->em->flush();

        $this->client->request('GET', '/admin/contact/?subject=not-a-real-subject');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('table.admin-table');
    }

    public function testEdgeInvalidStatusNoErrorAndDoesNotFilter(): void
    {
        $this->createAdminAndLogin();

        $this->createContact([
            'fullname' => 'A',
            'email' => 'a@example.com',
            'subject' => 'payment',
            'message' => 'AAAAAAAAAA',
            'sentAt' => new \DateTimeImmutable('2024-06-01 10:00:00'),
            'readAt' => null,
            'handled' => false,
        ]);
        $this->createContact([
            'fullname' => 'B',
            'email' => 'b@example.com',
            'subject' => 'login',
            'message' => 'BBBBBBBBBB',
            'sentAt' => new \DateTimeImmutable('2024-06-02 10:00:00'),
            'readAt' => new \DateTimeImmutable('2024-06-02 11:00:00'),
            'handled' => false,
        ]);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/admin/contact/?status=weird_status');
        $this->assertResponseIsSuccessful();

        $rows = $crawler->filter('table.admin-table tbody tr');
        $this->assertSame(2, $rows->count());
    }

    public function testEdgeQWithSpecialCharsNoError(): void
    {
        $this->createAdminAndLogin();

        $this->createContact([
            'fullname' => 'Special',
            'email' => 'special@example.com',
            'subject' => 'other',
            'message' => 'Message avec des caractères spéciaux: % _ " \' < > & é è à',
            'sentAt' => new \DateTimeImmutable('2024-07-01 10:00:00'),
        ]);
        $this->em->flush();

        $this->client->request('GET', '/admin/contact/?q=%25_%22%27%3C%3E%26%C3%A9');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('table.admin-table');
    }

    // -----------------------
    // Helpers
    // -----------------------

    private function createAdminAndLogin(): User
    {
        $admin = (new User())
            ->setEmail('admin+' . uniqid('', true) . '@example.com')
            ->setFirstName('Test')
            ->setLastName('Admin')
            ->setIsVerified(true)
            ->setStoredRoles(['ROLE_ADMIN'])
            ->setPassword('dummy');

        $this->em->persist($admin);
        $this->em->flush();

        $this->client->loginUser($admin);

        return $admin;
    }

    /**
     * @param array{
     *   fullname?: string,
     *   email?: string,
     *   subject?: string,
     *   message?: string,
     *   sentAt?: \DateTimeImmutable,
     *   readAt?: \DateTimeImmutable|null,
     *   handled?: bool
     * } $data
     */
    private function createContact(array $data = []): Contact
    {
        $c = new Contact();
        $c->setFullname($data['fullname'] ?? 'John Doe');
        $c->setEmail($data['email'] ?? ('contact+' . uniqid('', true) . '@example.com'));
        $c->setSubject($data['subject'] ?? 'other');
        $c->setMessage($data['message'] ?? 'Default message content long enough');

        $c->setSentAt($data['sentAt'] ?? new \DateTimeImmutable());

        if (array_key_exists('readAt', $data)) {
            if ($data['readAt'] === null) {
                $c->markUnread();
            } else {
                $c->markRead();
            }
        }

        if (array_key_exists('handled', $data)) {
            $c->setHandled((bool) $data['handled']);
        }

        $this->em->persist($c);

        return $c;
    }

    private function purgeContacts(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\Contact c')->execute();
    }

    private function purgeUsers(): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
    }
}