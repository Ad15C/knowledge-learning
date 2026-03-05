<?php

namespace App\Tests\Controller\Admin;

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
        $dbTools->get()->loadFixtures([TestUserFixtures::class]);
    }

    private function loginAsAdmin(): User
    {
        $admin = $this->em->getRepository(User::class)->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);
        self::assertNotNull($admin, 'Admin fixture introuvable.');
        $this->client->loginUser($admin);
        return $admin;
    }

    private function loginAsUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);
        self::assertNotNull($user, 'User fixture introuvable.');
        $this->client->loginUser($user);
        return $user;
    }

    private function createContact(
        string $email,
        string $fullname = 'Client Test',
        string $subject = 'payment',
        string $message = 'Bonjour, ceci est un message de test suffisamment long.'
    ): Contact {
        $c = (new Contact())
            ->setEmail($email)
            ->setFullname($fullname)
            ->setSubject($subject)
            ->setMessage($message);

        // sentAt est fixé par le listener prePersist
        $this->em->persist($c);
        $this->em->flush();

        return $c;
    }

    /**
     * Récupère le token CSRF depuis le HTML de la page index (méthode la plus robuste en tests).
     * $action = 'read' | 'unread' | 'handled'
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

    /**
     * POST avec Referer sûr (évite redirect vers une route POST-only si Referer absent).
     */
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

        // Peut être 302 (security), voire 301/302 selon ta config -> on reste souple
        self::assertTrue($this->client->getResponse()->isRedirection());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $html = (string) $this->client->getResponse()->getContent();
        self::assertTrue(
            str_contains($html, 'Connexion') || str_contains($html, 'Se connecter') || str_contains($html, 'login'),
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

        $c = $this->createContact('method@test.io');
        $id = $c->getId();

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

        $c = $this->createContact('show@test.io', 'Show Client', 'payment');
        self::assertNull($c->getReadAt());

        $this->client->request('GET', '/admin/contact/' . $c->getId());
        self::assertResponseIsSuccessful();

        $reloaded = $this->em->getRepository(Contact::class)->find($c->getId());
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

        $c = $this->createContact('markread@test.io');
        $id = $c->getId();

        // Sans CSRF => 403
        $this->postWithReferer("/admin/contact/{$id}/read");
        self::assertResponseStatusCodeSame(403);

        // Avec CSRF récupéré depuis l'index (où le formulaire est rendu)
        $token = $this->getCsrfTokenForContactAction($id, 'read');

        $this->postWithReferer("/admin/contact/{$id}/read", ['_token' => $token]);
        self::assertTrue($this->client->getResponse()->isRedirection());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->getReadAt());
        self::assertTrue($reloaded->isRead());
        self::assertSame('read', $reloaded->getStatus());
    }

    // -------------------------
    // mark_unread (POST)
    // -------------------------

    public function testPostMarkUnreadRequiresCsrfAndPersists(): void
    {
        $this->loginAsAdmin();

        $c = $this->createContact('markunread@test.io');
        $id = $c->getId();

        // Force lu
        $c->markRead();
        $this->em->flush();

        // Sans CSRF => 403
        $this->postWithReferer("/admin/contact/{$id}/unread");
        self::assertResponseStatusCodeSame(403);

        $token = $this->getCsrfTokenForContactAction($id, 'unread');

        $this->postWithReferer("/admin/contact/{$id}/unread", ['_token' => $token]);
        self::assertTrue($this->client->getResponse()->isRedirection());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertNull($reloaded->getReadAt());
        self::assertFalse($reloaded->isRead());
        self::assertSame('unread', $reloaded->getStatus());
    }

    // -------------------------
    // mark_handled (POST)
    // -------------------------

    public function testPostMarkHandledRequiresCsrfAndPersists(): void
    {
        $this->loginAsAdmin();

        $c = $this->createContact('markhandled@test.io');
        $id = $c->getId();

        // Sans CSRF => 403
        $this->postWithReferer("/admin/contact/{$id}/handled");
        self::assertResponseStatusCodeSame(403);

        $token = $this->getCsrfTokenForContactAction($id, 'handled');

        $this->postWithReferer("/admin/contact/{$id}/handled", ['_token' => $token]);
        self::assertTrue($this->client->getResponse()->isRedirection());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $reloaded = $this->em->getRepository(Contact::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isHandled());
        self::assertNotNull($reloaded->getHandledAt());
        self::assertSame('handled', $reloaded->getStatus());
    }

    // -------------------------
    // Filters (subject/status/q)
    // -------------------------

    public function testIndexFiltersBySubjectStatusAndSearchQ(): void
    {
        $this->loginAsAdmin();

        $this->createContact('payment@test.io', 'Client Payment', 'payment');
        $c2 = $this->createContact('login@test.io', 'Client Login', 'login');
        $c3 = $this->createContact('handled@test.io', 'Client Handled', 'other');

        $c2->markRead();
        $c3->setHandled(true);
        $this->em->flush();

        // subject=login => c2 uniquement
        $this->client->request('GET', '/admin/contact/?subject=login');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('login@test.io', $html);
        self::assertStringNotContainsString('payment@test.io', $html);

        // status=unread => payment uniquement
        $this->client->request('GET', '/admin/contact/?status=unread');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('payment@test.io', $html);
        self::assertStringNotContainsString('login@test.io', $html);
        self::assertStringNotContainsString('handled@test.io', $html);

        // status=read => login uniquement
        $this->client->request('GET', '/admin/contact/?status=read');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('login@test.io', $html);
        self::assertStringNotContainsString('payment@test.io', $html);
        self::assertStringNotContainsString('handled@test.io', $html);

        // status=handled => handled uniquement
        $this->client->request('GET', '/admin/contact/?status=handled');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('handled@test.io', $html);
        self::assertStringNotContainsString('payment@test.io', $html);
        self::assertStringNotContainsString('login@test.io', $html);

        // q => recherche
        $this->client->request('GET', '/admin/contact/?q=Client%20Payment');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('payment@test.io', $html);
        self::assertStringNotContainsString('login@test.io', $html);
    }
}