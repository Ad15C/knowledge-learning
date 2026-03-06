<?php

namespace App\Tests\Controller;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Certification;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\User;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LessonControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private ReferenceRepository $refRepo;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();
        $fixtureExecutor = $databaseTool->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        $this->refRepo = $fixtureExecutor->getReferenceRepository();
        $this->em->clear();
    }

    private function forceOrderNumber(Purchase $purchase): void
    {
        $ref = new \ReflectionClass(Purchase::class);
        $propOrderNumber = $ref->getProperty('orderNumber');
        $propOrderNumber->setAccessible(true);
        $propOrderNumber->setValue(
            $purchase,
            'ORD-TEST-' . date('YmdHis') . '-' . bin2hex(random_bytes(4))
        );
    }

    private function getFixtureUser(): User
    {
        $user = $this->refRepo->getReference(TestUserFixtures::USER_REF, User::class);
        self::assertInstanceOf(User::class, $user);

        $managed = $this->em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($managed);

        return $managed;
    }

    private function getFixtureLesson(): Lesson
    {
        $lesson = $this->refRepo->getReference(ThemeFixtures::LESSON_GUITAR_1_REF, Lesson::class);
        self::assertInstanceOf(Lesson::class, $lesson);

        $managed = $this->em->getRepository(Lesson::class)->find($lesson->getId());
        self::assertNotNull($managed);

        return $managed;
    }

    private function createPaidPurchaseForLesson(User $user, Lesson $lesson): void
    {
        $purchase = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable());

        $this->forceOrderNumber($purchase);

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setLesson($lesson)
            ->setQuantity(1)
            ->setUnitPrice((float) $lesson->getPrice());

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->persist($item);
        $this->em->flush();
        $this->em->clear();
    }

    private function fetchCsrfTokenFromLessonPage(int $lessonId): string
    {
        $crawler = $this->client->request('GET', '/lesson/' . $lessonId);
        self::assertResponseIsSuccessful();

        $tokenNode = $crawler->filter('form[action$="/lesson/' . $lessonId . '/complete"] input[name="_token"]');
        self::assertGreaterThan(0, $tokenNode->count(), 'CSRF token input not found in complete form.');

        $token = $tokenNode->attr('value');
        self::assertNotEmpty($token);

        return $token;
    }

    public function testShowWithoutPurchaseRedirectsToCursusWithFlash(): void
    {
        $user = $this->getFixtureUser();
        $lesson = $this->getFixtureLesson();
        $cursus = $lesson->getCursus();

        self::assertNotNull($cursus);

        $this->client->loginUser($user);
        $this->client->request('GET', '/lesson/' . $lesson->getId());

        self::assertResponseRedirects('/cursus/' . $cursus->getId());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', "Tu n'as pas accès à cette leçon.");
    }

    public function testShowWithPaidPurchaseShowsCompleteButton(): void
    {
        $user = $this->getFixtureUser();
        $lesson = $this->getFixtureLesson();

        $this->createPaidPurchaseForLesson($user, $lesson);

        $user = $this->em->getRepository(User::class)->find($user->getId());
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());

        self::assertNotNull($user);
        self::assertNotNull($lesson);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/lesson/' . $lesson->getId());

        self::assertResponseIsSuccessful();

        self::assertGreaterThan(
            0,
            $crawler->filter('form[action$="/lesson/' . $lesson->getId() . '/complete"] button')->count(),
            'Complete button should be visible when purchase is paid.'
        );

        self::assertStringContainsString(
            'Marquer la leçon comme complétée',
            $crawler->filter('form[action$="/lesson/' . $lesson->getId() . '/complete"] button')->text()
        );
    }

    public function testCompleteWithoutAccessWithInvalidCsrfReturns403(): void
    {
        $user = $this->getFixtureUser();
        $lesson = $this->getFixtureLesson();

        $this->client->loginUser($user);

        $this->client->request('POST', '/lesson/' . $lesson->getId() . '/complete', [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testCompleteWithoutAccessWithValidCsrfRedirectsToCursus(): void
    {
        $user = $this->getFixtureUser();
        $lesson = $this->getFixtureLesson();
        $cursus = $lesson->getCursus();

        self::assertNotNull($cursus);

        // 1. On crée temporairement un achat payé pour pouvoir afficher la page
        // et récupérer un vrai token CSRF depuis le formulaire.
        $this->createPaidPurchaseForLesson($user, $lesson);

        $user = $this->em->getRepository(User::class)->find($user->getId());
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());

        self::assertNotNull($user);
        self::assertNotNull($lesson);

        $this->client->loginUser($user);

        $token = $this->fetchCsrfTokenFromLessonPage($lesson->getId());

        // 2. On supprime ensuite l'achat pour retirer l'accès métier,
        // tout en gardant le token CSRF de la même session.
        $items = $this->em->getRepository(PurchaseItem::class)->findBy([
            'lesson' => $lesson,
        ]);

        foreach ($items as $item) {
            $purchase = $item->getPurchase();
            $this->em->remove($item);

            if ($purchase !== null) {
                $this->em->remove($purchase);
            }
        }

        $this->em->flush();
        $this->em->clear();

        $lesson = $this->getFixtureLesson();
        $cursus = $lesson->getCursus();

        self::assertNotNull($cursus);

        // 3. Le token est valide, mais l'accès n'existe plus :
        // le contrôleur doit rediriger vers le cursus.
        $this->client->request('POST', '/lesson/' . $lesson->getId() . '/complete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/cursus/' . $cursus->getId());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', "Tu n'as pas accès à cette leçon.");
    }

    public function testCompleteMarksLessonCompletedAndCreatesCertification(): void
    {
        $user = $this->getFixtureUser();
        $lesson = $this->getFixtureLesson();

        $this->createPaidPurchaseForLesson($user, $lesson);

        $user = $this->em->getRepository(User::class)->find($user->getId());
        $lesson = $this->em->getRepository(Lesson::class)->find($lesson->getId());

        self::assertNotNull($user);
        self::assertNotNull($lesson);

        $this->client->loginUser($user);

        $token = $this->fetchCsrfTokenFromLessonPage($lesson->getId());

        $this->client->request('POST', '/lesson/' . $lesson->getId() . '/complete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/lesson/' . $lesson->getId());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.lesson-page', 'Vous avez déjà complété cette leçon.');

        $this->em->clear();

        $userReloaded = $this->em->getRepository(User::class)->find($user->getId());
        $lessonReloaded = $this->em->getRepository(Lesson::class)->find($lesson->getId());

        self::assertNotNull($userReloaded);
        self::assertNotNull($lessonReloaded);

        $cert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userReloaded,
            'lesson' => $lessonReloaded,
            'type' => 'lesson',
        ]);

        self::assertNotNull($cert);
        self::assertNotEmpty($cert->getCertificateCode());
    }
}