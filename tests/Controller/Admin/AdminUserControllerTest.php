<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AdminUserControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private CsrfTokenManagerInterface $csrf;
    private $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->csrf = static::getContainer()->get(CsrfTokenManagerInterface::class);

        $this->databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();

        $this->databaseTool->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);
    }

    private function getAdmin(): User
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture not found.');
        return $admin;
    }

    private function getUser(): User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture not found.');
        return $user;
    }

    private function loginAsAdmin(): void
    {
        $this->client->loginUser($this->getAdmin());
    }

    private function loginAsUser(): void
    {
        $this->client->loginUser($this->getUser());
    }

    private function assertAnonymousRedirectsToLogin(): void
    {
        self::assertTrue($this->client->getResponse()->isRedirection(), 'Anonymous should be redirected.');
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
    }

    /**
     * Token CSRF récupéré depuis le HTML de l'index (robuste car même session réelle).
     *
     * @param string $status active|archived|all
     * @param int $userId
     * @param string $op delete|restore
     */
    private function tokenFromIndex(string $status, int $userId, string $op): string
    {
        $crawler = $this->client->request(
            'GET',
            sprintf('https://localhost/admin/users?action=delete&status=%s', $status)
        );
        self::assertResponseIsSuccessful();

        $action = sprintf('/admin/users/%d/%s', $userId, $op);

        $tokenInput = $crawler->filter(sprintf('form[action="%s"] input[name="_token"]', $action));
        self::assertGreaterThan(
            0,
            $tokenInput->count(),
            sprintf('CSRF input not found for form[action="%s"]', $action)
        );

        $token = (string) $tokenInput->attr('value');
        self::assertNotSame('', $token, 'CSRF token is empty.');

        return $token;
    }

    private function tokenFromManagerUsingClientSession(string $tokenId): string
    {
        // Initialise une vraie requête pour avoir cookie + session côté client
        $this->client->request('GET', 'https://localhost/admin/users?action=delete&status=active');
        self::assertResponseIsSuccessful();

        $container = static::getContainer();

        /** @var \Symfony\Component\HttpFoundation\Session\SessionFactoryInterface $sessionFactory */
        $sessionFactory = $container->get('session.factory');

        $tmpSession = $sessionFactory->createSession();
        $sessionName = $tmpSession->getName();

        $cookie = $this->client->getCookieJar()->get($sessionName);
        self::assertNotNull($cookie, 'Session cookie not found on client.');

        $sessionId = $cookie->getValue();

        // Session Symfony pointant sur la même session que le navigateur (même ID)
        $session = $sessionFactory->createSession();
        if (method_exists($session, 'setId')) {
            $session->setId($sessionId);
        }
        if (!$session->isStarted()) {
            $session->start();
        }

        $requestStack = $container->get('request_stack');

        $req = \Symfony\Component\HttpFoundation\Request::create('https://localhost/');
        $req->setSession($session);
        $requestStack->push($req);

        try {
            $token = $this->csrf->getToken($tokenId)->getValue();

            // ✅ CRUCIAL : persist le token dans la session (sinon le POST ne le verra pas)
            $session->save();

            return $token;
        } finally {
            $requestStack->pop();
        }
    }

    // ---------------------------
    // INDEX: GET /admin/users
    // ---------------------------

    public function testIndexOkAsAdmin(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/users');
        self::assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        self::assertTrue(
            str_contains($content, 'Gestion des clients')
            || str_contains($content, 'Utilisateurs')
            || str_contains($content, 'Clients'),
            'Expected users admin page content to mention users.'
        );
    }

    public function testIndexRedirectsWhenAnonymous(): void
    {
        $this->client->request('GET', 'https://localhost/admin/users');
        $this->assertAnonymousRedirectsToLogin();
    }

    public function testIndexForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $this->client->request('GET', 'https://localhost/admin/users');
        self::assertResponseStatusCodeSame(403);
    }

    // ---------------------------
    // SHOW: GET /admin/users/{id}
    // ---------------------------

    public function testShowOkAsAdmin(): void
    {
        $this->loginAsAdmin();

        $user = $this->getUser();
        $id = (int) $user->getId();

        $this->client->request('GET', sprintf('https://localhost/admin/users/%d', $id));
        self::assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString((string) $user->getEmail(), $content);
    }

    public function testShowRedirectsWhenAnonymous(): void
    {
        $id = (int) $this->getUser()->getId();

        $this->client->request('GET', sprintf('https://localhost/admin/users/%d', $id));
        $this->assertAnonymousRedirectsToLogin();
    }

    public function testShowForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $id = (int) $this->getUser()->getId();
        $this->client->request('GET', sprintf('https://localhost/admin/users/%d', $id));
        self::assertResponseStatusCodeSame(403);
    }

    // ---------------------------
    // EDIT: GET|POST /admin/users/{id}/edit
    // ---------------------------

    public function testEditGetOkAsAdmin(): void
    {
        $this->loginAsAdmin();

        $user = $this->getUser();
        $id = (int) $user->getId();

        $this->client->request('GET', sprintf('https://localhost/admin/users/%d/edit', $id));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="user[firstName]"]');
        self::assertSelectorExists('input[name="user[lastName]"]');
        self::assertSelectorExists('input[name="user[email]"]');
    }

    public function testEditPostValidUpdatesUserAndRedirects(): void
    {
        $this->loginAsAdmin();

        $user = $this->getUser();
        $id = (int) $user->getId();

        $crawler = $this->client->request('GET', sprintf('https://localhost/admin/users/%d/edit', $id));
        self::assertResponseIsSuccessful();

        $formNode = $crawler->selectButton('Enregistrer');
        $form = $formNode->count() > 0 ? $formNode->form() : $crawler->filter('form')->first()->form();

        $newFirst = 'EditedFirst_' . substr(uniqid('', true), 0, 8);
        $newLast  = 'EditedLast_' . substr(uniqid('', true), 0, 8);

        $form['user[firstName]']->setValue($newFirst);
        $form['user[lastName]']->setValue($newLast);
        $form['user[email]']->setValue((string) $user->getEmail());

        $this->client->submit($form);

        self::assertTrue($this->client->getResponse()->isRedirection(), 'Expected redirect after valid edit.');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Profil mis à jour.');

        $this->em->clear();
        /** @var User|null $reloaded */
        $reloaded = $this->em->getRepository(User::class)->find($id);
        self::assertNotNull($reloaded);

        self::assertSame($newFirst, $reloaded->getFirstName());
        self::assertSame($newLast, $reloaded->getLastName());
    }

    public function testEditRedirectsWhenAnonymous(): void
    {
        $id = (int) $this->getUser()->getId();

        $this->client->request('GET', sprintf('https://localhost/admin/users/%d/edit', $id));
        $this->assertAnonymousRedirectsToLogin();
    }

    public function testEditForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $id = (int) $this->getUser()->getId();

        $this->client->request('GET', sprintf('https://localhost/admin/users/%d/edit', $id));
        self::assertResponseStatusCodeSame(403);
    }

    // ---------------------------
    // DELETE (archive): POST /admin/users/{id}/delete
    // ---------------------------

    public function testDeleteArchivesNormalUserWithValidCsrf(): void
    {
        $this->loginAsAdmin();

        $user = $this->getUser();
        $id = (int) $user->getId();

        $user->setArchivedAt(null);
        $this->em->flush();
        $this->em->clear();

        $token = $this->tokenFromIndex('active', $id, 'delete');

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/delete', $id), [
            '_token' => $token,
        ]);

        self::assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Utilisateur archivé.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isArchived());
    }

    public function testDeleteInvalidCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/delete', $id), [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteSelfIsRejectedAndDoesNotArchiveAdmin(): void
    {
        $this->loginAsAdmin();

        $admin = $this->getAdmin();
        $id = (int) $admin->getId();

        $admin->setArchivedAt(null);
        $this->em->flush();
        $this->em->clear();

        $token = $this->tokenFromManagerUsingClientSession('archive_user_'.$id);

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/delete', $id), [
            '_token' => $token,
        ]);

        self::assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-danger');
        self::assertSelectorTextContains('.flash-messages .flash.flash-danger', 'Tu ne peux pas archiver ton propre compte.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isArchived());
    }

    public function testDeleteRedirectsWhenAnonymous(): void
    {
        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/delete', $id), [
            '_token' => 'whatever',
        ]);

        $this->assertAnonymousRedirectsToLogin();
    }

    public function testDeleteForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/delete', $id), [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    // ---------------------------
    // RESTORE: POST /admin/users/{id}/restore
    // ---------------------------

    public function testRestoreUnarchivesUserWithValidCsrf(): void
    {
        $this->loginAsAdmin();

        $user = $this->getUser();
        $id = (int) $user->getId();

        $user->setArchivedAt(new \DateTimeImmutable('-1 day'));
        $this->em->flush();
        $this->em->clear();

        $token = $this->tokenFromIndex('archived', $id, 'restore');

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/restore', $id), [
            '_token' => $token,
        ]);

        self::assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Utilisateur restauré.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isArchived());
    }

    public function testRestoreInvalidCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/restore', $id), [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRestoreRedirectsWhenAnonymous(): void
    {
        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/restore', $id), [
            '_token' => 'whatever',
        ]);

        $this->assertAnonymousRedirectsToLogin();
    }

    public function testRestoreForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/restore', $id), [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }
}