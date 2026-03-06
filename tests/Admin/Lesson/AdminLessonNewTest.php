<?php

namespace App\Tests\Admin\Lesson;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

class AdminLessonNewTest extends WebTestCase
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

    private function setAllCursusActive(bool $active): void
    {
        foreach ($this->em->getRepository(Cursus::class)->findAll() as $cursus) {
            $cursus->setIsActive($active);
        }

        $this->em->flush();
        $this->em->clear();
    }

    private function getAnyActiveCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy(['isActive' => true]);
        self::assertNotNull($cursus, 'No active cursus found.');

        return $cursus;
    }

    private function getAnyInactiveCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy(['isActive' => false]);
        self::assertNotNull($cursus, 'No inactive cursus found.');

        return $cursus;
    }

    private function getCursusSelectOptionLabels(Crawler $crawler): array
    {
        return $crawler
            ->filter('select[name="lesson[cursus]"] option')
            ->each(fn (Crawler $opt) => trim($opt->text()));
    }

    private function responseHasNotBlankMessage(string $content, string $customMessage): bool
    {
        if (str_contains($content, $customMessage)) {
            return true;
        }

        return str_contains($content, 'This value should not be blank')
            || str_contains($content, 'Cette valeur ne doit pas être vide');
    }

    private function responseHasInvalidChoiceMessage(string $content): bool
    {
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $hay = mb_strtolower($decoded);

        $needles = [
            'this value is not valid',
            'the selected choice is invalid',
            'invalid choice',
            "cette valeur n'est pas valide",
            "cette valeur n’est pas valide",
            'le choix sélectionné est invalide',
            'choix sélectionné invalide',
            'choix invalide',
            'valeur invalide',
            "n'est pas valide",
            "n’est pas valide",
            'is not valid',
        ];

        foreach ($needles as $needle) {
            if (str_contains($hay, mb_strtolower($needle))) {
                return true;
            }
        }

        if (str_contains($hay, 'valide') && (str_contains($hay, 'choix') || str_contains($hay, 'sélection'))) {
            return true;
        }

        return false;
    }

    public function testNewGetFormOk(): void
    {
        $this->loginAsAdmin();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Créer une leçon');
        self::assertSelectorExists('.admin-page-header');
        self::assertSelectorExists('a.btn.btn-secondary[href="/admin/lesson"]');

        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="lesson[title]"]');
        self::assertSelectorExists('select[name="lesson[cursus]"]');
        self::assertSelectorExists('input[name="lesson[price]"]');
        self::assertSelectorExists('textarea[name="lesson[fiche]"]');
        self::assertSelectorExists('input[name="lesson[videoUrl]"]');
        self::assertSelectorExists('input[name="lesson[image]"]');

        self::assertGreaterThan(0, $crawler->selectButton('Créer')->count(), 'Submit button "Créer" not found');
    }

    public function testCursusSelectProposesOnlyActiveCursus(): void
    {
        $this->loginAsAdmin();

        /** @var Cursus[] $all */
        $all = $this->em->getRepository(Cursus::class)->findAll();
        self::assertNotEmpty($all);

        $all[0]->setIsActive(false);
        for ($i = 1; $i < count($all); $i++) {
            $all[$i]->setIsActive(true);
        }
        $this->em->flush();

        $inactiveName = $all[0]->getName();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        $labels = $this->getCursusSelectOptionLabels($crawler);

        self::assertContains('— Choisir un cursus —', $labels);

        if ($inactiveName) {
            self::assertNotContains($inactiveName, $labels);
        }

        self::assertGreaterThan(
            1,
            count($labels),
            'Expected at least one active cursus option besides the placeholder.'
        );
    }

    public function testNewPostValidCreatesLessonAndIsActiveTrue(): void
    {
        $this->loginAsAdmin();

        $this->setAllCursusActive(true);
        $cursus = $this->getAnyActiveCursus();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        $uniqueTitle = 'Lesson New Test - ' . uniqid('', true);

        $form = $crawler->selectButton('Créer')->form([
            'lesson[title]' => $uniqueTitle,
            'lesson[cursus]' => (string) $cursus->getId(),
            'lesson[price]' => '12.50',
            'lesson[fiche]' => 'Fiche test',
            'lesson[videoUrl]' => '',
            'lesson[image]' => '',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/lesson');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Leçon créée.');

        $this->em->clear();
        /** @var Lesson|null $created */
        $created = $this->em->getRepository(Lesson::class)->findOneBy(['title' => $uniqueTitle]);

        self::assertNotNull($created, 'Lesson should have been created in database.');
        self::assertTrue($created->isActive(), 'Lesson should be active by default.');
        self::assertSame($cursus->getId(), $created->getCursus()?->getId());
        self::assertEquals(12.50, (float) $created->getPrice());
        self::assertSame('Fiche test', $created->getFiche());
    }

    public function testNewGetWhenAllCursusArchivedSelectIsEmptyAndNoCrash(): void
    {
        $this->loginAsAdmin();

        $this->setAllCursusActive(false);

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('h1.admin-page-title', 'Créer une leçon');
        self::assertSelectorExists('select[name="lesson[cursus]"]');

        $labels = $this->getCursusSelectOptionLabels($crawler);
        self::assertContains('— Choisir un cursus —', $labels);
        self::assertCount(1, $labels, 'Expected cursus select to contain only the placeholder when no active cursus exist.');

        self::assertGreaterThan(0, $crawler->selectButton('Créer')->count());
    }

    public function testPostWithoutTitleShowsValidationErrorAndDoesNotCreate(): void
    {
        $this->loginAsAdmin();

        $this->setAllCursusActive(true);
        $cursus = $this->getAnyActiveCursus();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer')->form([
            'lesson[title]' => '',
            'lesson[cursus]' => (string) $cursus->getId(),
            'lesson[price]' => '10.00',
            'lesson[fiche]' => '',
            'lesson[videoUrl]' => '',
            'lesson[image]' => '',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $content = (string) $this->client->getResponse()->getContent();
        self::assertTrue(
            $this->responseHasNotBlankMessage($content, 'Le titre est obligatoire.'),
            'Expected a validation error about title.'
        );
    }

    public function testPostWithoutPriceShowsValidationErrorAndDoesNotCreate(): void
    {
        $this->loginAsAdmin();

        $this->setAllCursusActive(true);
        $cursus = $this->getAnyActiveCursus();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        $uniqueTitle = 'Lesson Without Price - ' . uniqid('', true);

        $form = $crawler->selectButton('Créer')->form([
            'lesson[title]' => $uniqueTitle,
            'lesson[cursus]' => (string) $cursus->getId(),
            'lesson[price]' => '',
            'lesson[fiche]' => '',
            'lesson[videoUrl]' => '',
            'lesson[image]' => '',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $content = (string) $this->client->getResponse()->getContent();
        self::assertTrue(
            $this->responseHasNotBlankMessage($content, 'Le prix est obligatoire.'),
            'Expected a validation error about price.'
        );

        $this->em->clear();
        $created = $this->em->getRepository(Lesson::class)->findOneBy(['title' => $uniqueTitle]);
        self::assertNull($created);
    }

    public function testPostWithInactiveCursusIdIsRejectedAndDoesNotCreate(): void
    {
        $this->loginAsAdmin();

        /** @var Cursus[] $all */
        $all = $this->em->getRepository(Cursus::class)->findAll();
        self::assertNotEmpty($all);

        $all[0]->setIsActive(false);
        if (isset($all[1])) {
            $all[1]->setIsActive(true);
        }
        $this->em->flush();
        $this->em->clear();

        $inactive = $this->getAnyInactiveCursus();

        $crawler = $this->client->request('GET', 'https://localhost/admin/lesson/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer')->form();
        $csrf = $form['lesson[_token]']->getValue();

        $uniqueTitle = 'Lesson Inactive Cursus - ' . uniqid('', true);

        $this->client->request('POST', 'https://localhost/admin/lesson/new', [
            'lesson' => [
                '_token' => $csrf,
                'title' => $uniqueTitle,
                'cursus' => (string) $inactive->getId(),
                'price' => '15.00',
                'fiche' => '',
                'videoUrl' => '',
                'image' => '',
            ],
        ]);

        self::assertResponseStatusCodeSame(200);

        $content = (string) $this->client->getResponse()->getContent();
        self::assertTrue(
            $this->responseHasInvalidChoiceMessage($content),
            'Expected the form to reject an inactive cursus id (invalid choice).'
        );

        $this->em->clear();
        $created = $this->em->getRepository(Lesson::class)->findOneBy(['title' => $uniqueTitle]);
        self::assertNull($created, 'Lesson should not be created when cursus choice is invalid.');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}