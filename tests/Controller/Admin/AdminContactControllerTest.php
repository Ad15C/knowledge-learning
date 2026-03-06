<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\ContactFixtures;
use App\DataFixtures\TestUserFixtures;
use App\Entity\Contact;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminContactControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        // Simule HTTPS (sinon redirection 301 vers https://localhost/...)
        $this->client->setServerParameter('HTTPS', 'on');
        $this->client->setServerParameter('HTTP_HOST', 'localhost');

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var DatabaseToolCollection $dbTools */
        $dbTools = static::getContainer()->get(DatabaseToolCollection::class);
        $dbTools->get()->loadFixtures([
            TestUserFixtures::class,
            ContactFixtures::class,
        ]);
    }

    private function loginAsAdmin(): User
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture introuvable.');
        $this->client->loginUser($admin, 'main');

        return $admin;
    }

    private function loginAsUser(): User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture introuvable.');
        $this->client->loginUser($user, 'main');

        return $user;
    }

    private function createContact(
        string $email,
        string $fullname = 'Client Test',
        string $subject = 'payment',
        string $message = 'Bonjour, ceci est un message de test suffisamment long.'
    ): Contact {
        $contact = (new Contact())
            ->setEmail($email)
            ->setFullname($fullname)
            ->setSubject($subject)
            ->setMessage($message)
            ->setSentAt(new \DateTimeImmutable());

        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    /**
     * Récupère le token CSRF depuis le HTML de la page index.
     * $action = read|unread|handled
     */
    private function getCsrfTokenForContactAction(int $contactId, string $action): string
    {
        $crawler = $this->client->request('GET', '/admin/contact/');
        self::assertResponseIsSuccessful();

        $formAction = "/admin/contact/{$contactId}/{$action}";
        $input = $crawler->filter(sprintf('form[action="%s"] input[name="_token"]', $formAction));

        self::assertGreaterThan(
            0,
            $input->count(),
            sprintf('Token CSRF introuvable dans le formulaire %s', $formAction)
        );

        return (string) $input->attr('value');
    }

    private function postWithReferer(string $uri, array $params = [], string $referer = '/admin/contact/'): void
    {
        $this->client->request(
            'POST',
            $uri,
            $params,
            [],
            [
                'HTTP_REFERER' => 'https://localhost' . $referer,
                'HTTPS' => 'on',
                'HTTP_HOST' => 'localhost',
            ]
        );
    }

    // -------------------------
    // Accès / rôles
    // -------------------------

    public function testIndexAnonymousRedirectsToLogin(): void
    {
        $this->client->request('GET', '/admin/contact/');
        self::assertTrue($this->client->getResponse()->isRedirection());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertTrue(
            str_contains($html, 'Connexion')
            || str_contains($html, 'Se connecter')
            || str_contains($html, 'login'),
            'Après redirect, on ne semble pas être sur une page de login.'
        );
    }

    public function testIndexForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $this->client->request('GET', '/admin/contact/');
        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexOkForAdmin(): void
    {
        $this->loginAsAdmin();

        $this->createContact('alpha@test.io', 'Alpha Client', 'payment');
        $this->createContact('beta@test.io', 'Beta Client', 'login');

        $crawler = $this->client->request('GET', '/admin/contact/');
        self::assertResponseIsSuccessful();

        self::assertGreaterThan(0, $crawler->filter('table.admin-table')->count());
        self::assertStringContainsString('Messages contact', $crawler->filter('h1')->text(''));

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('alpha@test.io', $html);
        self::assertStringContainsString('beta@test.io', $html);
    }

    // -------------------------
    // Vérif méthodes HTTP (405)
    // -------------------------

    public function testPostOnlyRoutesReturn405WhenCalledWithGet(): void
    {
        $this->loginAsAdmin();

        $contact = $this->createContact('method@test.io');
        $id = $contact->getId();

        $this->client->request('GET', "/admin/contact/{$id}/read");
        self::assertResponseStatusCodeSame(405);

        $this->client->request('GET', "/admin/contact/{$id}/unread");
        self::assertResponseStatusCodeSame(405);

        $this->client->request('GET', "/admin/contact/{$id}/handled");
        self::assertResponseStatusCodeSame(405);
    }

    // -------------------------
    // show + auto markRead
    // -------------------------

    public function testShowMarksAsReadWhenOpening(): void
    {
        $this->loginAsAdmin();

        $contact = $this->createContact('show@test.io', 'Show Client', 'payment');
        self::assertNull($contact->getReadAt());

        $this->client->request('GET', '/admin/contact/' . $contact->getId());
        self::assertResponseIsSuccessful();

        $reloaded = $this->em->getRepository(Contact::class)->find($contact->getId());
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getReadAt(), 'La route show devrait marquer le message comme lu.');
        self::assertTrue($reloaded->isRead());
        self::assertSame('read', $reloaded->getStatus());
    }

    public function testShow404WhenContactDoesNotExist(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', '/admin/contact/999999999');
        self::assertResponseStatusCodeSame(404);
    }

    // -------------------------
    // mark_read (POST)
    // -------------------------

    public function testPostMarkReadRequiresCsrfAndPersists(): void
    {
        $this->loginAsAdmin();

        $contact = $this->createContact('markread@test.io');
        $id = $contact->getId();

        $this->postWithReferer("/admin/contact/{$id}/read");
        self::assertResponseStatusCodeSame(403);

        $token = $this->getCsrfTokenForContactAction($id, 'read');

        $this->postWithReferer("/admin/contact/{$id}/read", ['_token' => $token]);
        self::assertTrue($this->client->getResponse()->isRedirection());

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getReadAt());
        self::assertTrue($reloaded->isRead());
        self::assertSame('read', $reloaded->getStatus());
    }

    public function testPostMarkReadWithInvalidCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $contact = $this->createContact('markread-invalid@test.io');
        $id = $contact->getId();

        $this->postWithReferer("/admin/contact/{$id}/read", ['_token' => 'invalid-token']);
        self::assertResponseStatusCodeSame(403);

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getReadAt());
        self::assertFalse($reloaded->isRead());
    }

    public function testPostMarkReadForbiddenForRoleUserEvenWithValidCsrf(): void
    {
        $this->loginAsAdmin();
        $contact = $this->createContact('markread-user@test.io');
        $id = $contact->getId();
        $token = $this->getCsrfTokenForContactAction($id, 'read');

        $this->loginAsUser();
        $this->postWithReferer("/admin/contact/{$id}/read", ['_token' => $token]);

        self::assertResponseStatusCodeSame(403);

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getReadAt());
        self::assertFalse($reloaded->isRead());
    }

    public function testPostMarkReadRedirectsAnonymousToLogin(): void
    {
        $contact = $this->createContact('markread-anon@test.io');
        $id = $contact->getId();

        $this->postWithReferer("/admin/contact/{$id}/read", ['_token' => 'whatever']);
        self::assertTrue($this->client->getResponse()->isRedirection());

        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
    }

    // -------------------------
    // mark_unread (POST)
    // -------------------------

    public function testPostMarkUnreadRequiresCsrfAndPersists(): void
    {
        $this->loginAsAdmin();

        $contact = $this->createContact('markunread@test.io');
        $id = $contact->getId();

        $contact->markRead();
        $this->em->flush();

        $this->postWithReferer("/admin/contact/{$id}/unread");
        self::assertResponseStatusCodeSame(403);

        $token = $this->getCsrfTokenForContactAction($id, 'unread');

        $this->postWithReferer("/admin/contact/{$id}/unread", ['_token' => $token]);
        self::assertTrue($this->client->getResponse()->isRedirection());

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getReadAt());
        self::assertFalse($reloaded->isRead());
        self::assertSame('unread', $reloaded->getStatus());
    }

    public function testPostMarkUnreadWithInvalidCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $contact = $this->createContact('markunread-invalid@test.io');
        $id = $contact->getId();

        $contact->markRead();
        $this->em->flush();

        $this->postWithReferer("/admin/contact/{$id}/unread", ['_token' => 'invalid-token']);
        self::assertResponseStatusCodeSame(403);

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isRead());
        self::assertNotNull($reloaded->getReadAt());
    }

    public function testPostMarkUnreadForbiddenForRoleUserEvenWithValidCsrf(): void
    {
        $this->loginAsAdmin();
        $contact = $this->createContact('markunread-user@test.io');
        $id = $contact->getId();
        $contact->markRead();
        $this->em->flush();

        $token = $this->getCsrfTokenForContactAction($id, 'unread');

        $this->loginAsUser();
        $this->postWithReferer("/admin/contact/{$id}/unread", ['_token' => $token]);

        self::assertResponseStatusCodeSame(403);

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isRead());
        self::assertNotNull($reloaded->getReadAt());
    }

    public function testPostMarkUnreadRedirectsAnonymousToLogin(): void
    {
        $contact = $this->createContact('markunread-anon@test.io');
        $id = $contact->getId();
        $contact->markRead();
        $this->em->flush();

        $this->postWithReferer("/admin/contact/{$id}/unread", ['_token' => 'whatever']);
        self::assertTrue($this->client->getResponse()->isRedirection());

        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
    }

    // -------------------------
    // mark_handled (POST)
    // -------------------------

    public function testPostMarkHandledRequiresCsrfAndPersists(): void
    {
        $this->loginAsAdmin();

        $contact = $this->createContact('markhandled@test.io');
        $id = $contact->getId();

        $this->postWithReferer("/admin/contact/{$id}/handled");
        self::assertResponseStatusCodeSame(403);

        $token = $this->getCsrfTokenForContactAction($id, 'handled');

        $this->postWithReferer("/admin/contact/{$id}/handled", ['_token' => $token]);
        self::assertTrue($this->client->getResponse()->isRedirection());

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isHandled());
        self::assertNotNull($reloaded->getHandledAt());
        self::assertSame('handled', $reloaded->getStatus());
    }

    public function testPostMarkHandledWithInvalidCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $contact = $this->createContact('markhandled-invalid@test.io');
        $id = $contact->getId();

        $this->postWithReferer("/admin/contact/{$id}/handled", ['_token' => 'invalid-token']);
        self::assertResponseStatusCodeSame(403);

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isHandled());
        self::assertNull($reloaded->getHandledAt());
    }

    public function testPostMarkHandledForbiddenForRoleUserEvenWithValidCsrf(): void
    {
        $this->loginAsAdmin();
        $contact = $this->createContact('markhandled-user@test.io');
        $id = $contact->getId();
        $token = $this->getCsrfTokenForContactAction($id, 'handled');

        $this->loginAsUser();
        $this->postWithReferer("/admin/contact/{$id}/handled", ['_token' => $token]);

        self::assertResponseStatusCodeSame(403);

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isHandled());
        self::assertNull($reloaded->getHandledAt());
    }

    public function testPostMarkHandledRedirectsAnonymousToLogin(): void
    {
        $contact = $this->createContact('markhandled-anon@test.io');
        $id = $contact->getId();

        $this->postWithReferer("/admin/contact/{$id}/handled", ['_token' => 'whatever']);
        self::assertTrue($this->client->getResponse()->isRedirection());

        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
    }

    // -------------------------
    // Filters (subject/status/q/email)
    // -------------------------

    public function testIndexFiltersBySubjectStatusAndSearchQ(): void
    {
        $this->loginAsAdmin();

        $this->createContact('payment@test.io', 'Client Payment', 'payment');
        $contactRead = $this->createContact('login@test.io', 'Client Login', 'login');
        $contactHandled = $this->createContact('handled@test.io', 'Client Handled', 'other');

        $contactRead->markRead();
        $contactHandled->setHandled(true);
        $this->em->flush();

        $this->client->request('GET', '/admin/contact/?subject=login');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('login@test.io', $html);
        self::assertStringNotContainsString('payment@test.io', $html);

        $this->client->request('GET', '/admin/contact/?status=unread');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('payment@test.io', $html);
        self::assertStringNotContainsString('login@test.io', $html);
        self::assertStringNotContainsString('handled@test.io', $html);

        $this->client->request('GET', '/admin/contact/?status=read');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('login@test.io', $html);
        self::assertStringNotContainsString('payment@test.io', $html);
        self::assertStringNotContainsString('handled@test.io', $html);

        $this->client->request('GET', '/admin/contact/?status=handled');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('handled@test.io', $html);
        self::assertStringNotContainsString('payment@test.io', $html);
        self::assertStringNotContainsString('login@test.io', $html);

        $this->client->request('GET', '/admin/contact/?q=Client%20Payment');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('payment@test.io', $html);
        self::assertStringNotContainsString('login@test.io', $html);
    }

    public function testIndexFilterByEmail(): void
    {
        $this->loginAsAdmin();

        $this->createContact('alpha.client@test.io', 'Alpha Client', 'payment');
        $this->createContact('beta.client@test.io', 'Beta Client', 'login');

        $this->client->request('GET', '/admin/contact/?email=beta.client@test.io');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('beta.client@test.io', $html);
        self::assertStringNotContainsString('alpha.client@test.io', $html);
    }

    public function testIndexFilterByEmailIsMergedWithQ(): void
    {
        $this->loginAsAdmin();

        $this->createContact('john.doe@test.io', 'John Doe', 'payment');
        $this->createContact('jane.doe@test.io', 'Jane Doe', 'payment');

        $this->client->request('GET', '/admin/contact/?q=Jane&email=jane.doe@test.io');
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('jane.doe@test.io', $html);
        self::assertStringNotContainsString('john.doe@test.io', $html);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}