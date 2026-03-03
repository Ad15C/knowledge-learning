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
        /** @var User $user */
        $user = $this->refRepo->getReference(TestUserFixtures::USER_REF, User::class);
        self::assertInstanceOf(User::class, $user);

        return $this->em->getRepository(User::class)->find($user->getId());
    }

    private function getFixtureLesson(): Lesson
    {
        /** @var Lesson $lesson */
        $lesson = $this->refRepo->getReference(ThemeFixtures::LESSON_GUITAR_1_REF, Lesson::class);
        self::assertInstanceOf(Lesson::class, $lesson);

        return $this->em->getRepository(Lesson::class)->find($lesson->getId());
    }

    private function createPaidPurchaseForLesson(User $user, Lesson $lesson): void
    {
        $purchase = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable());

        // orderNumber obligatoire (NOT NULL + unique)
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
    }

    public function testShowWithoutPurchaseShowsNoAccessMessage(): void
    {
        $user = $this->getFixtureUser();
        $lesson = $this->getFixtureLesson();

        $this->client->loginUser($user);
        $this->client->request('GET', '/lesson/' . $lesson->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.lesson-page', "Vous n'avez pas encore accès à cette leçon.");
    }

    public function testShowWithPaidPurchaseShowsCompleteButton(): void
    {
        $user = $this->getFixtureUser();
        $lesson = $this->getFixtureLesson();

        $this->createPaidPurchaseForLesson($user, $lesson);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/lesson/' . $lesson->getId());

        self::assertResponseIsSuccessful();

        self::assertGreaterThan(
            0,
            $crawler->filter('form[action$="/lesson/'.$lesson->getId().'/complete"] button:contains("Marquer comme complétée")')->count(),
            'Complete button should be visible when purchase is paid.'
        );
    }

    public function testCompleteMarksLessonCompletedAndCreatesCertification(): void
    {
        $user = $this->getFixtureUser();
        $lesson = $this->getFixtureLesson();

        $this->createPaidPurchaseForLesson($user, $lesson);

        $this->client->loginUser($user);

        // IMPORTANT: démarre une session (CSRF TokenManager utilise la session)
        $crawler = $this->client->request('GET', '/lesson/' . $lesson->getId());
        self::assertResponseIsSuccessful();

        // Récupère directement le token depuis le champ caché (encore plus fiable)
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        self::assertNotEmpty($token);

        $this->client->request('POST', '/lesson/' . $lesson->getId() . '/complete', [
            '_token' => $token,
        ]);

        self::assertResponseRedirects('/lesson/' . $lesson->getId());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('.lesson-page', 'Vous avez déjà complété cette leçon.');

        $cert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);

        self::assertNotNull($cert);
        self::assertNotEmpty($cert->getCertificateCode());
    }
}