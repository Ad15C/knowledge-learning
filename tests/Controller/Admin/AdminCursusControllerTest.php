<?php

namespace App\Tests\Controller\Admin;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class AdminCursusControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private CsrfTokenManagerInterface $csrf;
    private $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
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

    private function loginAsAdmin(): void
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin);
        $this->client->loginUser($admin, 'main');
    }

    private function loginAsUser(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user);
        $this->client->loginUser($user, 'main');
    }

    private function extractCsrfTokenFromCrawler(Crawler $crawler, string $formSelector = 'form'): string
    {
        $tokenNode = $crawler->filter($formSelector . ' input[name="_token"]')->first();
        self::assertGreaterThan(0, $tokenNode->count());

        $token = (string) $tokenNode->attr('value');
        self::assertNotEmpty($token);

        return $token;
    }

    private function getDisableTokenForCursus(int $id): string
    {
        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $id . '/delete');
        self::assertResponseIsSuccessful();

        $formSelector = sprintf('form[action="/admin/cursus/%d/disable"][method="post"]', $id);
        self::assertSelectorExists($formSelector);

        return $this->extractCsrfTokenFromCrawler($crawler, $formSelector);
    }

    private function getActivateTokenForCursusFromArchivedList(int $id): string
    {
        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus?status=archived');
        self::assertResponseIsSuccessful();

        $formSelector = sprintf('form[action="/admin/cursus/%d/activate"]', $id);
        self::assertSelectorExists($formSelector);

        return $this->extractCsrfTokenFromCrawler($crawler, $formSelector);
    }

    private function getStableActiveTheme(): Theme
    {
        $theme = $this->em->getRepository(Theme::class)->findOneBy(['name' => 'Musique']);
        self::assertNotNull($theme);

        return $theme;
    }

    private function getAnyCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy([]);
        self::assertNotNull($cursus);

        return $cursus;
    }

    private function tokenFromManagerUsingClientSession(string $tokenId, string $warmupUrl): string
    {
        $this->client->request('GET', $warmupUrl);

        $container = static::getContainer();

        $sessionFactory = $container->get('session.factory');
        $tmpSession = $sessionFactory->createSession();
        $sessionName = $tmpSession->getName();

        $cookie = $this->client->getCookieJar()->get($sessionName);
        self::assertNotNull($cookie);

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

    public function testIndexIsReachableForAdmin(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/cursus');
        self::assertResponseIsSuccessful();
    }

    public function testNewPageIsReachableForAdmin(): void
    {
        $this->loginAsAdmin();

        $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();
    }

    public function testNewPostCreatesCursusWhenThereIsActiveTheme(): void
    {
        $this->loginAsAdmin();

        $theme = $this->getStableActiveTheme();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();

        $form['cursus[name]'] = 'Cursus Test Create';
        $form['cursus[theme]'] = (string) $theme->getId();
        $form['cursus[price]'] = '99.90';
        $form['cursus[description]'] = 'Desc test';
        $form['cursus[image]'] = '';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/cursus');

        $this->em->clear();
        $created = $this->em->getRepository(Cursus::class)->findOneBy(['name' => 'Cursus Test Create']);
        self::assertNotNull($created);
        self::assertTrue($created->isActive());
    }

    public function testDisableRedirectsWhenAnonymous(): void
    {
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => 'whatever',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testActivateRedirectsWhenAnonymous(): void
    {
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => 'whatever',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testDisableForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivateForbiddenForRoleUser(): void
    {
        $this->loginAsUser();

        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisableForbiddenForRoleUserEvenWithValidCsrf(): void
    {
        $this->loginAsUser();

        $cursus = $this->getAnyCursus();

        $token = $this->tokenFromManagerUsingClientSession(
            'cursus_disable' . $cursus->getId(),
            'https://localhost/dashboard'
        );

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivateForbiddenForRoleUserEvenWithValidCsrf(): void
    {
        $this->loginAsUser();

        $cursus = $this->getAnyCursus();

        $token = $this->tokenFromManagerUsingClientSession(
            'cursus_activate' . $cursus->getId(),
            'https://localhost/dashboard'
        );

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisableRequiresValidCsrf(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => 'bad',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisableMissingCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisableWithValidCsrfArchivesCursus(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setIsActive(true);
        $this->em->flush();

        $token = $this->getDisableTokenForCursus($id);

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $id . '/disable', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/cursus');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
    }

    public function testActivateRequiresValidCsrf(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => 'bad',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivateMissingCsrfReturns403(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate');

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivateWithValidCsrfReactivatesCursus(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setIsActive(false);
        $this->em->flush();

        $token = $this->getActivateTokenForCursusFromArchivedList($id);

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $id . '/activate', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/cursus');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}