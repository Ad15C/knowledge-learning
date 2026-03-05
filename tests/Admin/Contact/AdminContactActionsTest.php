<?php

namespace App\Tests\Admin\Contact;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminContactActionsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

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

    // -----------------------
    // CSRF invalide => 403
    // -----------------------

    public function testMarkReadWithInvalidCsrfReturns403(): void
    {
        $this->createAdminAndLogin();

        $c = $this->createContact(['readAt' => null, 'handled' => false]);
        $this->em->flush();

        $this->client->request('POST', sprintf('/admin/contact/%d/read', $c->getId()), [
            '_token' => 'bad-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testMarkUnreadWithInvalidCsrfReturns403(): void
    {
        $this->createAdminAndLogin();

        $c = $this->createContact([
            'readAt' => new \DateTimeImmutable('2024-01-01 12:00:00'),
            'handled' => false,
        ]);
        $this->em->flush();

        $this->client->request('POST', sprintf('/admin/contact/%d/unread', $c->getId()), [
            '_token' => 'bad-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testMarkHandledWithInvalidCsrfReturns403(): void
    {
        $this->createAdminAndLogin();

        $c = $this->createContact(['readAt' => null, 'handled' => false]);
        $this->em->flush();

        $this->client->request('POST', sprintf('/admin/contact/%d/handled', $c->getId()), [
            '_token' => 'bad-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // -----------------------
    // Redirect: referer si présent sinon index
    // -----------------------

    public function testMarkReadRedirectsToRefererIfProvided(): void
    {
        $this->createAdminAndLogin();

        $c = $this->createContact(['readAt' => null, 'handled' => false]);
        $this->em->flush();

        $token = $this->getTokenFromIndexForm($c->getId(), 'read');

        $this->client->request(
            'POST',
            sprintf('/admin/contact/%d/read', $c->getId()),
            ['_token' => $token],
            [],
            ['HTTP_REFERER' => 'https://localhost/admin/contact/?status=unread']
        );

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/admin/contact/?status=unread', $this->client->getRequest()->getUri());
    }

    public function testMarkReadRedirectsToIndexIfNoReferer(): void
    {
        $this->createAdminAndLogin();

        $c = $this->createContact(['readAt' => null, 'handled' => false]);
        $this->em->flush();

        $token = $this->getTokenFromIndexForm($c->getId(), 'read');

        $this->client->request('POST', sprintf('/admin/contact/%d/read', $c->getId()), [
            '_token' => $token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/admin/contact/', $this->client->getRequest()->getUri());
    }

    // -----------------------
    // Effets métier
    // -----------------------

    public function testMarkReadWithValidCsrfSetsReadAtNow(): void
    {
        $this->createAdminAndLogin();

        $c = $this->createContact(['readAt' => null, 'handled' => false]);
        $this->em->flush();
        $id = $c->getId();

        $token = $this->getTokenFromIndexForm($id, 'read');

        $before = time();
        $this->client->request('POST', sprintf('/admin/contact/%d/read', $id), [
            '_token' => $token,
        ]);
        self::assertResponseIsSuccessful();

        $this->em->clear();
        /** @var Contact $reloaded */
        $reloaded = $this->em->getRepository(Contact::class)->find($id);

        self::assertNotNull($reloaded->getReadAt());

        $after = time();
        $ts = $reloaded->getReadAt()->getTimestamp();
        self::assertGreaterThanOrEqual($before - 2, $ts);
        self::assertLessThanOrEqual($after + 2, $ts);
    }

    public function testMarkUnreadWithValidCsrfSetsReadAtNull(): void
    {
        $this->createAdminAndLogin();

        $c = $this->createContact([
            'readAt' => new \DateTimeImmutable('2024-01-01 12:00:00'),
            'handled' => false,
        ]);
        $this->em->flush();
        $id = $c->getId();

        $token = $this->getTokenFromIndexForm($id, 'unread');

        $this->client->request('POST', sprintf('/admin/contact/%d/unread', $id), [
            '_token' => $token,
        ]);
        self::assertResponseIsSuccessful();

        $this->em->clear();
        /** @var Contact $reloaded */
        $reloaded = $this->em->getRepository(Contact::class)->find($id);

        self::assertNull($reloaded->getReadAt());
    }

    public function testMarkHandledWithValidCsrfSetsHandledTrueAndHandledAtIfNull(): void
    {
        $this->createAdminAndLogin();

        $c = $this->createContact(['readAt' => null, 'handled' => false]);
        $c->setHandledAt(null);
        $this->em->flush();
        $id = $c->getId();

        $token = $this->getTokenFromIndexForm($id, 'handled');

        $this->client->request('POST', sprintf('/admin/contact/%d/handled', $id), [
            '_token' => $token,
        ]);
        self::assertResponseIsSuccessful();

        $this->em->clear();
        /** @var Contact $reloaded */
        $reloaded = $this->em->getRepository(Contact::class)->find($id);

        self::assertTrue($reloaded->isHandled());
        self::assertNotNull($reloaded->getHandledAt());
    }

    // -----------------------
    // Edge logique: handled puis unread -> status handled + UI "Traité"
    // -----------------------

    public function testHandledThenMarkUnreadStillShowsHandledStatusInUi(): void
    {
        $this->createAdminAndLogin();

        $c = $this->createContact([
            'readAt' => new \DateTimeImmutable('2024-01-01 12:00:00'),
            'handled' => false,
        ]);
        $this->em->flush();
        $id = $c->getId();

        // 1) handled
        $tokenHandled = $this->getTokenFromIndexForm($id, 'handled');
        $this->client->request('POST', sprintf('/admin/contact/%d/handled', $id), [
            '_token' => $tokenHandled,
        ]);
        self::assertResponseIsSuccessful();

        // 2) unread
        $tokenUnread = $this->getTokenFromIndexForm($id, 'unread');
        $this->client->request('POST', sprintf('/admin/contact/%d/unread', $id), [
            '_token' => $tokenUnread,
        ]);
        self::assertResponseIsSuccessful();

        // 3) DB
        $this->em->clear();
        /** @var Contact $final */
        $final = $this->em->getRepository(Contact::class)->find($id);

        self::assertNull($final->getReadAt());
        self::assertTrue($final->isHandled());
        self::assertSame('handled', $final->getStatus());

        // 4) UI show
        $this->client->request('GET', sprintf('/admin/contact/%d', $id));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.badge.badge-active');
        self::assertSelectorTextContains('.badge.badge-active', 'Traité');
    }

    // -----------------------
    // Helpers
    // -----------------------

    private function getTokenFromIndexForm(int $contactId, string $action): string
    {
        // Va sur la page index (crée le contexte request + session)
        $crawler = $this->client->request('GET', '/admin/contact/');
        self::assertResponseIsSuccessful();

        $path = sprintf('/admin/contact/%d/%s', $contactId, $action);

        // Cherche un form dont l'action finit par /admin/contact/{id}/{action}
        $formNode = $crawler->filter(sprintf('form[action$="%s"]', $path));
        self::assertGreaterThan(
            0,
            $formNode->count(),
            sprintf('Form action "%s" introuvable sur la page index.', $path)
        );

        $tokenInput = $formNode->eq(0)->filter('input[name="_token"]');
        self::assertGreaterThan(0, $tokenInput->count(), 'Input CSRF _token introuvable dans le form.');

        $token = (string) $tokenInput->attr('value');
        self::assertNotSame('', $token, 'Token CSRF vide.');

        return $token;
    }

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

    private function createContact(array $data = []): Contact
    {
        $c = new Contact();
        $c->setFullname($data['fullname'] ?? 'John Doe');
        $c->setEmail($data['email'] ?? ('contact+' . uniqid('', true) . '@example.com'));
        $c->setSubject($data['subject'] ?? 'other');
        $c->setMessage($data['message'] ?? 'Default message content long enough');
        $c->setSentAt($data['sentAt'] ?? new \DateTimeImmutable('2024-01-01 10:00:00'));

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