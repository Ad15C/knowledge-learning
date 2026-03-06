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

class AdminLessonEditTest extends WebTestCase
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

    private function getAnyLesson(): Lesson
    {
        $lesson = $this->em->getRepository(Lesson::class)->findOneBy([]);
        self::assertNotNull($lesson, 'No lesson found in fixtures.');
        self::assertNotNull($lesson->getCursus(), 'Fixture lesson must have a cursus.');

        return $lesson;
    }

    private function getAnyActiveCursusExcept(?int $excludeId = null): Cursus
    {
        $qb = $this->em->getRepository(Cursus::class)->createQueryBuilder('c')
            ->andWhere('c.isActive = true');

        if ($excludeId) {
            $qb->andWhere('c.id != :id')->setParameter('id', $excludeId);
        }

        $cursus = $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();
        self::assertNotNull($cursus, 'No active cursus found (except excluded).');

        return $cursus;
    }

    private function getAnyInactiveCursusExcept(?int $excludeId = null): Cursus
    {
        $qb = $this->em->getRepository(Cursus::class)->createQueryBuilder('c')
            ->andWhere('c.isActive = false');

        if ($excludeId) {
            $qb->andWhere('c.id != :id')->setParameter('id', $excludeId);
        }

        $cursus = $qb->setMaxResults(1)->getQuery()->getOneOrNullResult();

        if (!$cursus) {
            $active = $this->getAnyActiveCursusExcept($excludeId);
            $active->setIsActive(false);
            $this->em->flush();
            $this->em->clear();

            $cursus = $this->em->getRepository(Cursus::class)->find($active->getId());
        }

        self::assertNotNull($cursus, 'No inactive cursus found/created.');

        return $cursus;
    }

    private function getSelectOptionLabels(Crawler $crawler, string $selectName): array
    {
        $labels = [];

        $crawler->filter(sprintf('select[name="%s"] option', $selectName))
            ->each(function (Crawler $opt) use (&$labels) {
                $labels[] = trim($opt->text());
            });

        return $labels;
    }

    private function getSelectedOptionValue(Crawler $crawler, string $selectName): ?string
    {
        $node = $crawler->filter(sprintf('select[name="%s"] option[selected]', $selectName));

        if ($node->count() === 0) {
            return null;
        }

        return $node->attr('value');
    }

    private function responseHasInvalidChoiceMessage(string $content): bool
    {
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $hay = mb_strtolower($decoded);

        $needles = [
            'this value is not valid',
            'the selected choice is invalid',
            'the value you selected is not a valid choice',
            'is not a valid choice',
            'selected choice is invalid',

            "cette valeur n'est pas valide",
            "cette valeur n’est pas valide",
            'le choix sélectionné est invalide',
            'le choix selectionné est invalide',
            "la valeur sélectionnée n'est pas valide",
            "la valeur sélectionnée n’est pas valide",
            "la valeur sélectionnée n'est pas un choix valide",
            "la valeur sélectionnée n’est pas un choix valide",
            'n’est pas un choix valide',
            "n'est pas un choix valide",
            'choix invalide',
            'valeur invalide',
        ];

        foreach ($needles as $needle) {
            if (str_contains($hay, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------
    // GET OK
    // -------------------------------------------------

    public function testEditGetOk(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $id = $lesson->getId();
        self::assertNotNull($id);

        $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $id));
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="lesson[title]"]');
        self::assertSelectorExists('select[name="lesson[cursus]"]');
        self::assertSelectorExists('input[name="lesson[price]"]');
        self::assertGreaterThan(0, $this->client->getCrawler()->selectButton('Enregistrer')->count());
    }

    // -------------------------------------------------
    // Cas important : le cursus courant est archivé
    // -------------------------------------------------

    public function testEditGetIncludesCurrentArchivedCursusAndDoesNotListOtherArchivedCursus(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lessonId = $lesson->getId();
        self::assertNotNull($lessonId);

        $currentCursus = $lesson->getCursus();
        self::assertNotNull($currentCursus);
        $currentId = $currentCursus->getId();
        self::assertNotNull($currentId);

        $currentCursus->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $otherArchived = $this->getAnyInactiveCursusExcept($currentId);
        $otherArchivedId = $otherArchived->getId();
        self::assertNotNull($otherArchivedId);

        $crawler = $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId));
        self::assertResponseIsSuccessful();

        $labels = $this->getSelectOptionLabels($crawler, 'lesson[cursus]');

        $currentName = $currentCursus->getName();
        if ($currentName) {
            self::assertContains($currentName, $labels, 'Current archived cursus should still be listed on edit.');
        }

        $otherName = $otherArchived->getName();
        if ($otherName) {
            self::assertNotContains($otherName, $labels, 'Other archived cursus should NOT be listed on edit.');
        }

        $selectedValue = $this->getSelectedOptionValue($crawler, 'lesson[cursus]');
        self::assertSame((string) $currentId, (string) $selectedValue, 'Current cursus should be selected by default.');
    }

    // -------------------------------------------------
    // POST OK : édition simple
    // -------------------------------------------------

    public function testEditPostOkWithCurrentArchivedCursusKeepsItAndUpdatesFields(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lessonId = $lesson->getId();
        self::assertNotNull($lessonId);

        $currentCursus = $lesson->getCursus();
        self::assertNotNull($currentCursus);
        $currentId = $currentCursus->getId();
        self::assertNotNull($currentId);

        $currentCursus->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId));
        self::assertResponseIsSuccessful();

        $newTitle = 'Edited Title - ' . uniqid('', true);

        $form = $crawler->selectButton('Enregistrer')->form([
            'lesson[title]' => $newTitle,
            'lesson[cursus]' => (string) $currentId,
            'lesson[price]' => '99.90',
            'lesson[fiche]' => 'Fiche edit',
            'lesson[videoUrl]' => '',
            'lesson[image]' => '',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/lesson');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.flash-messages .flash.flash-success');
        self::assertSelectorTextContains('.flash-messages .flash.flash-success', 'Leçon modifiée.');

        $this->em->clear();
        /** @var Lesson|null $updated */
        $updated = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($updated);

        self::assertSame($newTitle, $updated->getTitle());
        self::assertSame((string) $currentId, (string) $updated->getCursus()?->getId(), 'Cursus should remain the current (archived) one.');
        self::assertEquals(99.90, (float) $updated->getPrice());
    }

    // -------------------------------------------------
    // POST OK : déplacer la leçon vers un cursus actif
    // -------------------------------------------------

    public function testEditPostCanMoveLessonToAnotherActiveCursus(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lessonId = $lesson->getId();
        self::assertNotNull($lessonId);

        $currentCursus = $lesson->getCursus();
        self::assertNotNull($currentCursus);
        $currentId = $currentCursus->getId();
        self::assertNotNull($currentId);

        $target = $this->getAnyActiveCursusExcept($currentId);
        $targetId = $target->getId();
        self::assertNotNull($targetId);

        $crawler = $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId));
        self::assertResponseIsSuccessful();

        $newTitle = 'Moved Lesson - ' . uniqid('', true);

        $form = $crawler->selectButton('Enregistrer')->form([
            'lesson[title]' => $newTitle,
            'lesson[cursus]' => (string) $targetId,
            'lesson[price]' => '12.34',
            'lesson[fiche]' => '',
            'lesson[videoUrl]' => '',
            'lesson[image]' => '',
        ]);

        $this->client->submit($form);
        self::assertResponseRedirects('/admin/lesson');

        $this->em->clear();
        $updated = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($updated);
        self::assertSame((string) $targetId, (string) $updated->getCursus()?->getId());
        self::assertSame($newTitle, $updated->getTitle());
        self::assertEquals(12.34, (float) $updated->getPrice());
    }

    // -------------------------------------------------
    // Sécurité formulaire
    // -------------------------------------------------

    public function testEditPostRejectsOtherArchivedCursusId(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lessonId = $lesson->getId();
        self::assertNotNull($lessonId);

        $currentCursus = $lesson->getCursus();
        self::assertNotNull($currentCursus);
        $currentId = $currentCursus->getId();
        self::assertNotNull($currentId);

        $otherArchived = $this->getAnyInactiveCursusExcept($currentId);
        $otherArchivedId = $otherArchived->getId();
        self::assertNotNull($otherArchivedId);

        $crawler = $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form();
        $csrf = $form['lesson[_token]']->getValue();

        $originalTitle = $lesson->getTitle();

        $this->client->request('POST', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId), [
            'lesson' => [
                '_token' => $csrf,
                'title' => 'Should Not Apply - ' . uniqid('', true),
                'cursus' => (string) $otherArchivedId,
                'price' => '10.00',
                'fiche' => '',
                'videoUrl' => '',
                'image' => '',
            ],
        ]);

        self::assertResponseStatusCodeSame(200);

        $content = (string) $this->client->getResponse()->getContent();
        self::assertTrue(
            $this->responseHasInvalidChoiceMessage($content),
            'Expected an "invalid choice" validation error when posting an archived cursus not equal to the current one.'
        );

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($lessonId);
        self::assertNotNull($reloaded);

        self::assertSame($originalTitle, $reloaded->getTitle(), 'Lesson should not be modified when form is invalid.');
        self::assertNotSame((string) $otherArchivedId, (string) $reloaded->getCursus()?->getId(), 'Lesson cursus should not be set to invalid archived cursus.');
    }

    public function testEditAnonymousIsRedirectedToLogin(): void
    {
        $lesson = $this->getAnyLesson();
        $lessonId = $lesson->getId();
        self::assertNotNull($lessonId);

        $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId));

        self::assertTrue(
            $this->client->getResponse()->isRedirection(),
            'Anonymous user should be redirected.'
        );

        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testEditUserWithoutAdminRoleIsForbidden(): void
    {
        $user = $this->em->getRepository(User::class)
            ->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);

        self::assertNotNull($user);
        $this->client->loginUser($user);

        $lesson = $this->getAnyLesson();
        $lessonId = $lesson->getId();
        self::assertNotNull($lessonId);

        $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId));

        self::assertResponseStatusCodeSame(403);
    }

    public function testEditPostWithoutTitleShowsValidationErrorAndDoesNotUpdate(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lessonId = $lesson->getId();
        self::assertNotNull($lessonId);

        $originalTitle = $lesson->getTitle();

        $crawler = $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'lesson[title]' => '',
            'lesson[cursus]' => (string) $lesson->getCursus()?->getId(),
            'lesson[price]' => '10.00',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $content = (string) $this->client->getResponse()->getContent();
        self::assertTrue(
            str_contains($content, 'Le titre est obligatoire.')
            || str_contains($content, 'This value should not be blank')
            || str_contains($content, 'Cette valeur ne doit pas être vide'),
            'Expected NotBlank validation error for title.'
        );

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($lessonId);

        self::assertSame($originalTitle, $reloaded?->getTitle());
    }

    public function testEditPostWithoutPriceShowsValidationErrorAndDoesNotUpdate(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lessonId = $lesson->getId();
        self::assertNotNull($lessonId);

        $originalPrice = $lesson->getPrice();

        $crawler = $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'lesson[title]' => $lesson->getTitle(),
            'lesson[cursus]' => (string) $lesson->getCursus()?->getId(),
            'lesson[price]' => '',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $content = (string) $this->client->getResponse()->getContent();
        self::assertTrue(
            str_contains($content, 'Le prix est obligatoire.')
            || str_contains($content, 'This value should not be blank')
            || str_contains($content, 'Cette valeur ne doit pas être vide'),
            'Expected NotBlank validation error for price.'
        );

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($lessonId);

        self::assertSame($originalPrice, $reloaded?->getPrice());
    }

    public function testEditPostWithInvalidCsrfTokenDoesNotUpdate(): void
    {
        $this->loginAsAdmin();

        $lesson = $this->getAnyLesson();
        $lessonId = $lesson->getId();
        self::assertNotNull($lessonId);

        $originalTitle = $lesson->getTitle();

        $crawler = $this->client->request('GET', sprintf('https://localhost/admin/lesson/%d/edit', $lessonId));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'lesson[title]' => 'Should Not Save',
            'lesson[cursus]' => (string) $lesson->getCursus()?->getId(),
            'lesson[price]' => '15.00',
        ]);

        $form['lesson[_token]'] = 'invalid_token';

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Lesson::class)->find($lessonId);

        self::assertSame($originalTitle, $reloaded?->getTitle());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }
    }
}