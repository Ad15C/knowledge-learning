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
        $this->client->loginUser($this->getAdmin(), 'main');
    }

    private function loginAsUser(): void
    {
        $this->client->loginUser($this->getUser(), 'main');
    }

    private function assertAnonymousRedirectsToLogin(): void
    {
        self::assertTrue($this->client->getResponse()->isRedirection(), 'Anonymous should be redirected.');
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
    }

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

    private function tokenFromManagerUsingClientSession(string $tokenId, string $warmupUrl): string
    {
        $this->client->request('GET', $warmupUrl);

        $container = static::getContainer();

        $sessionFactory = $container->get('session.factory');

        $tmpSession = $sessionFactory->createSession();
        $sessionName = $tmpSession->getName();

        $cookie = $this->client->getCookieJar()->get($sessionName);
        self::assertNotNull($cookie, 'Session cookie not found on client.');

        $sessionId = $cookie->getValue();

        $session = $sessionFactory->createSession();
        if (method_exists($session, 'setId')) {
            $session->setId($sessionId);
        }
        if (!$session->isStarted()) {
            $session->start();
        }

        $requestStack = $container->get('request_stack');

        $req = Request::create('https://localhost/');
        $req->setSession($session);
        $requestStack->push($req);

        try {
            $token = $this->csrf->getToken($tokenId)->getValue();
            $session->save();

            return $token;
        } finally {
            $requestStack->pop();
        }
    }

    public function testIndexOkAsAdmin(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/users');
        self::assertResponseIsSuccessful();
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

    public function testEditGetOkAsAdmin(): void
    {
        $this->loginAsAdmin();

        $user = $this->getUser();
        $id = (int) $user->getId();

        $this->client->request('GET', sprintf('https://localhost/admin/users/%d/edit', $id));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('form');
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

        self::assertTrue($this->client->getResponse()->isRedirection());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        $this->em->clear();
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

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isArchived());
    }

    public function testDeleteMissingCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/delete', $id));

        self::assertResponseStatusCodeSame(403);
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

    public function testDeleteForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/delete', $id), [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteForbiddenForRoleUserEvenWithValidCsrf(): void
    {
        $this->loginAsUser();

        $target = $this->getAdmin();
        $id = (int) $target->getId();

        $token = $this->tokenFromManagerUsingClientSession(
            'archive_user_' . $id,
            'https://localhost/dashboard'
        );

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/delete', $id), [
            '_token' => $token,
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

        $token = $this->tokenFromManagerUsingClientSession(
            'archive_user_' . $id,
            'https://localhost/admin/users'
        );

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/delete', $id), [
            '_token' => $token,
        ]);

        self::assertTrue($this->client->getResponse()->isRedirection());

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

        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isArchived());
    }

    public function testRestoreMissingCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/restore', $id));

        self::assertResponseStatusCodeSame(403);
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

    public function testRestoreForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $id = (int) $this->getUser()->getId();

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/restore', $id), [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRestoreForbiddenForRoleUserEvenWithValidCsrf(): void
    {
        $this->loginAsUser();

        $target = $this->getAdmin();
        $id = (int) $target->getId();

        $token = $this->tokenFromManagerUsingClientSession(
            'restore_user_' . $id,
            'https://localhost/dashboard'
        );

        $this->client->request('POST', sprintf('https://localhost/admin/users/%d/restore', $id), [
            '_token' => $token,
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

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}