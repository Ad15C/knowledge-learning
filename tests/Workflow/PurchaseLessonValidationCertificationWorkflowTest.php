<?php

namespace App\Tests\Workflow;

use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\Purchase;
use App\Entity\Theme;
use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PurchaseLessonValidationCertificationWorkflowTest extends WebTestCase
{
    use DoctrineSchemaTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($this->em);
    }

    public function testWorkflowLoginPurchasePayAccessCompleteAndGenerateCertificatesAndPdf(): void
    {
        // --- Arrange: create verified user + theme + cursus + 1 lesson
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('buyer@example.com');
        $user->setFirstName('Buyer');
        $user->setLastName('User');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);
        $user->setPassword($passwordHasher->hashPassword($user, 'Test1234Secure!'));

        $theme = new Theme();
        $theme->setName('Theme Test');

        $cursus = new Cursus();
        $cursus->setName('Cursus Test');
        $cursus->setPrice(49.99);
        $cursus->setTheme($theme);

        $lesson = new Lesson();
        $lesson->setTitle('Lesson 1');
        $lesson->setPrice(9.99);
        $lesson->setCursus($cursus);

        $this->em->persist($theme);
        $this->em->persist($cursus);
        $this->em->persist($lesson);
        $this->em->persist($user);
        $this->em->flush();

        // --- Login
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'buyer@example.com',
            '_password' => 'Test1234Secure!',
        ]);
        $this->client->submit($loginForm);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect(); // /dashboard
        $this->assertResponseIsSuccessful();

        // --- Add cursus to cart
        $this->client->request('GET', '/cart/add/cursus/' . $cursus->getId());
        $this->assertResponseRedirects('/cart');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Cart purchase exists
        $cartPurchase = $this->em->getRepository(Purchase::class)->findOneBy([
            'user' => $user,
            'status' => 'cart',
        ]);
        $this->assertNotNull($cartPurchase);
        $this->assertGreaterThanOrEqual(1, $cartPurchase->getItems()->count());

        // --- Pay (simulation)
        $this->client->request('GET', '/cart/pay');
        $this->assertTrue($this->client->getResponse()->isRedirection());

        $successUrl = $this->client->getResponse()->headers->get('Location');
        $this->assertNotEmpty($successUrl);
        $this->assertStringContainsString('/cart/success/', $successUrl);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Re-fetch managed entities after requests
        $this->em->clear();

        /** @var User $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'buyer@example.com']);
        $this->assertNotNull($user);

        /** @var Lesson $lesson */
        $lesson = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson 1']);
        $this->assertNotNull($lesson);

        /** @var Cursus $cursus */
        $cursus = $lesson->getCursus();
        $this->assertNotNull($cursus);

        /** @var Purchase|null $paidPurchase */
        $paidPurchase = $this->em->getRepository(Purchase::class)->findOneBy([
            'user' => $user,
            'status' => 'paid',
        ]);
        $this->assertNotNull($paidPurchase);
        $this->assertSame('paid', $paidPurchase->getStatus());
        $this->assertNotNull($paidPurchase->getPaidAt());

        // --- Access lesson page
        $this->client->request('GET', '/lesson/' . $lesson->getId());
        $this->assertResponseIsSuccessful();

        // --- Complete lesson
        $this->client->request('POST', '/lesson/' . $lesson->getId() . '/complete');
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Re-fetch again (flush happened during request)
        $this->em->clear();

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'buyer@example.com']);
        $lesson = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson 1']);
        $cursus = $lesson->getCursus();

        // --- Assert LessonValidated created
        $validated = $this->em->getRepository(LessonValidated::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
        ]);
        $this->assertNotNull($validated);

        // --- Assert lesson certification created
        $lessonCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);
        $this->assertNotNull($lessonCert);
        $this->assertNotEmpty($lessonCert->getCertificateCode());

        // --- Assert cursus certification created (cursus has 1 lesson => completed)
        $cursusCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);
        $this->assertNotNull($cursusCert);

        // --- PDF route returns application/pdf for lesson cert
        $this->client->request('GET', '/dashboard/certification/' . $lessonCert->getId() . '/pdf');
        $this->assertResponseStatusCodeSame(200);

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertTrue(
            str_contains($contentType, 'application/pdf'),
            'Le Content-Type doit contenir application/pdf, obtenu: ' . $contentType
        );
    }
}