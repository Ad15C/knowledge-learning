<?php

namespace App\Tests\Admin\Cursus;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AdminCursusDeleteTest extends WebTestCase
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

    private function loginAsUser(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user, 'User fixture not found.');
        $this->client->loginUser($user);
    }

    private function getAnyCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy([]);
        self::assertNotNull($cursus, 'No cursus found (fixtures missing?).');

        return $cursus;
    }

    private function extractHiddenToken(Crawler $crawler, string $formSelector, string $inputName = '_token'): string
    {
        $input = $crawler->filter($formSelector . ' input[name="' . $inputName . '"]');

        self::assertGreaterThan(
            0,
            $input->count(),
            sprintf('Hidden input "%s" not found in form "%s".', $inputName, $formSelector)
        );

        $token = (string) $input->first()->attr('value');
        self::assertNotSame('', $token, 'CSRF token value is empty.');

        return $token;
    }

    public function testDeleteConfirmGetRedirectsToLoginWhenNotLoggedIn(): void
    {
        $cursus = $this->getAnyCursus();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/delete');

        self::assertResponseRedirects('/login');
    }

    public function testDisablePostRedirectsToLoginWhenNotLoggedIn(): void
    {
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => 'whatever',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testActivatePostRedirectsToLoginWhenNotLoggedIn(): void
    {
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => 'whatever',
        ]);

        self::assertResponseRedirects('/login');
    }

    public function testDeleteConfirmGetIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $cursus = $this->getAnyCursus();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/delete');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisablePostIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/disable', [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testActivatePostIsForbiddenForRoleUser(): void
    {
        $this->loginAsUser();
        $cursus = $this->getAnyCursus();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $cursus->getId() . '/activate', [
            '_token' => 'whatever',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteConfirmPageIsSuccessful(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $cursus->setName('Cursus test suppression');
        $cursus->setDescription('Description de test');
        $cursus->setPrice(49.90);
        $this->em->flush();

        $id = $cursus->getId();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $id . '/delete');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Désactiver un cursus');
        self::assertSelectorTextContains('.theme-detail-title', 'Cursus test suppression');
        self::assertSelectorExists('form[action*="/admin/cursus/' . $id . '/disable"]');
        self::assertSelectorTextContains('.admin-alert-danger', 'Ce cursus sera désactivé');
        self::assertSelectorTextContains('button.btn.btn-danger', 'Confirmer la désactivation');
        self::assertSelectorExists('a.btn.btn-secondary[href="/admin/cursus"]');

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Thème', $content);
        self::assertStringContainsString('Prix', $content);
        self::assertStringContainsString('Description de test', $content);

        $this->extractHiddenToken($crawler, 'form[action*="/admin/cursus/' . $id . '/disable"]');
    }

    public function testDeleteConfirmPageWithoutDescriptionDoesNotCrash(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $cursus->setDescription(null);
        $this->em->flush();

        $this->client->request('GET', 'https://localhost/admin/cursus/' . $cursus->getId() . '/delete');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.admin-page-title', 'Désactiver un cursus');
    }

    public function testDisableWithValidCsrfSetsIsActiveFalse(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setIsActive(true);
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus/' . $id . '/delete');
        self::assertResponseIsSuccessful();

        $token = $this->extractHiddenToken($crawler, 'form[action*="/admin/cursus/' . $id . '/disable"]');

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

    public function testActivateWithValidCsrfSetsIsActiveTrue(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setIsActive(false);
        $this->em->flush();

        $crawler = $this->client->request('GET', 'https://localhost/admin/cursus?status=archived');
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('form[action*="/admin/cursus/' . $id . '/activate"]');

        $token = $this->extractHiddenToken($crawler, 'form[action*="/admin/cursus/' . $id . '/activate"]');

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

    public function testDisableWithInvalidCsrfReturns403AndDoesNotChangeState(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setIsActive(true);
        $this->em->flush();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $id . '/disable', [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isActive());
    }

    public function testActivateWithInvalidCsrfReturns403AndDoesNotChangeState(): void
    {
        $this->loginAsAdmin();

        $cursus = $this->getAnyCursus();
        $id = $cursus->getId();

        $cursus->setIsActive(false);
        $this->em->flush();

        $this->client->request('POST', 'https://localhost/admin/cursus/' . $id . '/activate', [
            '_token' => 'invalid_token',
        ]);

        self::assertResponseStatusCodeSame(403);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Cursus::class)->find($id);

        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}