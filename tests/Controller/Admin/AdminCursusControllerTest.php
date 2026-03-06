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
    private $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();

        // ThemeFixtures crée déjà des cursus + lessons
        $this->databaseTool->loadFixtures([
            TestUserFixtures::class,
            ThemeFixtures::class,
        ]);
    }

    private function loginAsAdmin(): void
    {
        $admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);

        self::assertNotNull($admin, 'Admin fixture not found.');
        $this->client->loginUser($admin);
    }

    private function extractCsrfTokenFromCrawler(Crawler $crawler, string $formSelector = 'form'): string
    {
        $tokenNode = $crawler->filter($formSelector . ' input[name="_token"]')->first();
        self::assertGreaterThan(0, $tokenNode->count(), 'CSRF token input not found.');

        $token = (string) $tokenNode->attr('value');
        self::assertNotEmpty($token, 'CSRF token value is empty.');

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
        self::assertNotNull($theme, 'Theme "Musique" not found in ThemeFixtures.');

        return $theme;
    }

    private function getAnyCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy([]);
        self::assertNotNull($cursus, 'No cursus found. ThemeFixtures should create some cursus.');

        return $cursus;
    }

    private function assertDecimalEquals(string $expected, string $actual, string $message = ''): void
    {
        self::assertEquals(
            (float) $expected,
            (float) $actual,
            $message !== '' ? $message : sprintf('Failed asserting decimal value %s equals %s.', $actual, $expected)
        );
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
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Cursus créé.');

        $this->em->clear();
        $created = $this->em->getRepository(Cursus::class)->findOneBy(['name' => 'Cursus Test Create']);

        self::assertNotNull($created);
        self::assertTrue($created->isActive());
        self::assertSame('Musique', $created->getTheme()?->getName());
        $this->assertDecimalEquals('99.90', $created->getPrice());
    }

    public function testNewPostRedirectsToThemesIfNoActiveTheme(): void
    {
        $this->loginAsAdmin();

        foreach ($this->em->getRepository(Theme::class)->findAll() as $t) {
            $t->setIsActive(false);
        }
        $this->em->flush();

        $this->client->request('POST', 'https://localhost/admin/cursus/new', [
            'cursus' => [
                'name' => 'X',
                'price' => '10.00',
            ],
        ]);

        self::assertResponseRedirects('/admin/themes');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-error');
        self::assertSelectorTextContains('.flash-messages .flash.flash-error', 'Aucun thème actif disponible');
    }

    public function testEditGetIsReachableForAdmin(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/edit');
        self::assertResponseIsSuccessful();
    }

    public function testEditPostUpdatesCursusAndRedirects(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $id . '/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form')->first()->form();

        $form['cursus[name]'] = 'Cursus (modifié)';
        $form['cursus[theme]'] = (string) $cursus->getTheme()?->getId();
        $form['cursus[price]'] = '123.45';

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/cursus');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Cursus modifié.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertSame('Cursus (modifié)', $reloaded->getName());
        $this->assertDecimalEquals('123.45', $reloaded->getPrice());
    }

    public function testDeleteConfirmPageIsReachableAndDoesNotChangeState(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setIsActive(true);
        $this->em->flush();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $id . '/delete');
        self::assertResponseIsSuccessful();

        $this->em->clear();
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive(), 'GET delete confirm should not archive the cursus.');
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
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Cursus archivé.');

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
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Cursus réactivé.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive());
    }

    public function testDisableAlreadyDisabledIsIdempotent(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setIsActive(false);
        $this->em->flush();

        $token = $this->getDisableTokenForCursus($id);

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $id . '/disable', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/cursus');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Cursus archivé.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
    }

    public function testActivateAlreadyActiveIsIdempotent(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setIsActive(true);
        $this->em->flush();

        $cursus->setIsActive(false);
        $this->em->flush();
        $token = $this->getActivateTokenForCursusFromArchivedList($id);

        $cursus = $this->em->getRepository(Cursus::class)->find($id);
        $cursus->setIsActive(true);
        $this->em->flush();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $id . '/activate', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/admin/cursus');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Cursus réactivé.');

        $this->em->clear();
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive());
    }
}