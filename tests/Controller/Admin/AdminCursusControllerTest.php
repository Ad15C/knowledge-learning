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

class AdminCursusControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient([], [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
            'SERVER_PORT' => 443,
        ]);

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var DatabaseToolCollection $dbTools */
        $dbTools = static::getContainer()->get(DatabaseToolCollection::class);
        $dbTools->get()->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);
    }

    private function getAdmin(): User
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture introuvable.');

        return $admin;
    }

    private function getUser(): User
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture introuvable.');

        return $user;
    }

    private function getStableActiveTheme(): Theme
    {
        $theme = $this->em->getRepository(Theme::class)
            ->findOneBy(['name' => 'Musique']);

        self::assertNotNull($theme, 'Thème "Musique" introuvable.');
        self::assertTrue($theme->isActive(), 'Le thème "Musique" devrait être actif.');

        return $theme;
    }

    private function getAnyCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy([]);

        self::assertNotNull($cursus, 'Aucun cursus fixture introuvable.');

        return $cursus;
    }

    private function extractCsrfTokenFromCrawler(Crawler $crawler, string $formSelector = 'form'): string
    {
        $tokenNode = $crawler->filter($formSelector . ' input[name="_token"]')->first();

        self::assertGreaterThan(
            0,
            $tokenNode->count(),
            sprintf('Aucun champ CSRF trouvé pour le sélecteur "%s".', $formSelector)
        );

        $token = (string) $tokenNode->attr('value');
        self::assertNotEmpty($token, 'Le token CSRF extrait est vide.');

        return $token;
    }

    private function getDisableTokenForCursus(int $id): string
    {
        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $id . '/delete');
        self::assertResponseIsSuccessful();

        $formSelector = sprintf('form[action="/admin/cursus/%d/disable"]', $id);
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

    public function testIndexAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', 'https://localhost/admin/cursus');

        self::assertResponseRedirects('/login');
    }

    public function testIndexAsUserIsForbidden(): void
    {
        $this->client->loginUser($this->getUser(), 'main');

        $this->client->request('GET', 'https://localhost/admin/cursus');

        self::assertResponseStatusCodeSame(403);
    }

    public function testIndexIsReachableForAdmin(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

        $this->client->request('GET', 'https://localhost/admin/cursus');

        self::assertResponseIsSuccessful();
    }

    public function testNewPageIsReachableForAdmin(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

        $this->client->request('GET', 'https://localhost/admin/cursus/new');

        self::assertResponseIsSuccessful();
    }

    public function testEditPageIsReachableForAdmin(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
        $cursus = $this->getAnyCursus();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit');

        self::assertResponseIsSuccessful();
    }

    public function testDeleteConfirmPageIsReachableForAdmin(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
        $cursus = $this->getAnyCursus();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/delete');

        self::assertResponseIsSuccessful();
    }

    public function testNewPostCreatesCursusWhenThereIsActiveTheme(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
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
        self::assertSame('Desc test', $created->getDescription());
    }

    public function testNewPostRedirectsToAdminThemeIndexWhenThereIsNoActiveTheme(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

        $themes = $this->em->getRepository(Theme::class)->findAll();
        foreach ($themes as $theme) {
            $theme->setIsActive(false);
        }
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();
        $form['cursus[name]'] = 'Cursus Impossible';
        $form['cursus[price]'] = '10.00';
        $form['cursus[description]'] = 'Desc';
        $form['cursus[image]'] = '';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/themes');

        $this->em->clear();
        $notCreated = $this->em->getRepository(Cursus::class)->findOneBy(['name' => 'Cursus Impossible']);
        self::assertNull($notCreated);
    }

    public function testEditPostUpdatesCursus(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $id . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();
        $form['cursus[name]'] = 'Cursus Modifié';
        $form['cursus[price]'] = '149.50';
        $form['cursus[description]'] = 'Nouvelle description';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/cursus');

        $this->em->clear();
        $updated = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($updated);
        self::assertSame('Cursus Modifié', $updated->getName());
        self::assertSame('Nouvelle description', $updated->getDescription());
    }

    public function testDisableAnonymousIsRedirectedToLogin(): void
    {
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => 'whatever',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testActivateAnonymousIsRedirectedToLogin(): void
    {
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => 'whatever',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testDisableAsUserIsForbidden(): void
    {
        $this->client->loginUser($this->getUser(), 'main');
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivateAsUserIsForbidden(): void
    {
        $this->client->loginUser($this->getUser(), 'main');
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisableRequiresValidCsrf(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => 'bad',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisableMissingCsrfReturns403(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisableWithValidCsrfArchivesCursus(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

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
        $this->client->loginUser($this->getAdmin(), 'main');
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => 'bad',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivateMissingCsrfReturns403(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate');

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivateWithValidCsrfReactivatesCursus(): void
    {
        $this->client->loginUser($this->getAdmin(), 'main');

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