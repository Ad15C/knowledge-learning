<?php

namespace App\Tests\Admin\Contact;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminContactShowTest extends WebTestCase
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

    public function testOpenUnreadMessageMarksItReadAndPersists(): void
    {
        $this->createAdminAndLogin();

        $contact = $this->createContact([
            'fullname' => 'Unread Person',
            'email' => 'unread@example.com',
            'subject' => 'other',
            'message' => 'Hello unread message (long enough)',
            'sentAt' => new \DateTimeImmutable('2024-01-01 10:00:00'),
            'readAt' => null,
            'handled' => false,
        ]);
        $this->em->flush();

        self::assertNull($contact->getReadAt(), 'Précondition: readAt doit être NULL avant ouverture.');

        $this->client->request('GET', sprintf('/admin/contact/%d', $contact->getId()));
        self::assertResponseIsSuccessful();

        // Recharge depuis DB pour vérifier la persistance
        $this->em->clear();
        /** @var Contact|null $reloaded */
        $reloaded = $this->em->getRepository(Contact::class)->find($contact->getId());
        self::assertNotNull($reloaded);

        self::assertNotNull($reloaded->getReadAt(), 'readAt doit être set après ouverture (persisté).');
    }

    public function testOpenAlreadyReadMessageDoesNotChangeReadAt(): void
    {
        $this->createAdminAndLogin();

        $initialReadAt = new \DateTimeImmutable('2024-02-01 12:34:56');

        $contact = $this->createContact([
            'fullname' => 'Read Person',
            'email' => 'read@example.com',
            'subject' => 'other',
            'message' => 'Hello read message (long enough)',
            'sentAt' => new \DateTimeImmutable('2024-02-01 10:00:00'),
            'handled' => false,
        ]);
        $contact->setReadAt($initialReadAt);

        $this->em->flush();
        $id = $contact->getId();
        self::assertNotNull($id);

        $this->client->request('GET', sprintf('/admin/contact/%d', $id));
        self::assertResponseIsSuccessful();

        $this->em->clear();
        /** @var Contact|null $reloaded */
        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);

        $reloadedReadAt = $reloaded->getReadAt();
        self::assertNotNull($reloadedReadAt);

        // Le controller ne doit PAS re-marquer lu si déjà lu -> readAt inchangé
        self::assertSame(
            $initialReadAt->getTimestamp(),
            $reloadedReadAt->getTimestamp(),
            'readAt ne doit pas être modifié quand le message est déjà lu.'
        );
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
            $c->setReadAt($data['readAt']);
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