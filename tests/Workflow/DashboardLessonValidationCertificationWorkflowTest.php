<?php

namespace App\Tests\Workflow;

use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\Theme;
use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DashboardLessonValidationCertificationWorkflowTest extends WebTestCase
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

    public function testDashboardToLessonCompleteGeneratesCertification(): void
    {
        // --- Arrange: verified user + theme + cursus + lesson
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setFirstName('User');
        $user->setLastName('Test');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);
        $user->setPassword($passwordHasher->hashPassword($user, 'Test1234Secure!'));

        $theme = new Theme();
        $theme->setName('Theme Dashboard');

        $cursus = new Cursus();
        $cursus->setName('Cursus Dashboard');
        $cursus->setPrice(10.00);
        $cursus->setTheme($theme);

        $lesson = new Lesson();
        $lesson->setTitle('Lesson Dashboard');
        $lesson->setPrice(0.00);
        $lesson->setCursus($cursus);

        $this->em->persist($theme);
        $this->em->persist($cursus);
        $this->em->persist($lesson);
        $this->em->persist($user);
        $this->em->flush();

        // IMPORTANT: le LessonController est protégé et vérifie aussi l'accès via PurchaseItem(status=paid).
        // Comme on veut tester "dashboard > lesson > validation > certification" sans achat,
        // on achète la leçon en DB en créant un Purchase "paid" + PurchaseItem(lesson).
        // (On réutilise tes entités Purchase / PurchaseItem)
        $purchase = new \App\Entity\Purchase();
        $purchase->setUser($user);
        $purchase->setStatus('paid');
        $purchase->setPaidAt(new \DateTimeImmutable());

        $item = new \App\Entity\PurchaseItem();
        $item->setPurchase($purchase);
        $item->setLesson($lesson);
        $item->setUnitPrice(0.00);

        $this->em->persist($purchase);
        $this->em->persist($item);
        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->flush();

        // --- Login
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'user@example.com',
            '_password' => 'Test1234Secure!',
        ]);
        $this->client->submit($loginForm);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect(); // /dashboard
        $this->assertResponseIsSuccessful();

        // --- Dashboard OK
        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        // --- Open lesson page (should be accessible)
        $this->client->request('GET', '/lesson/' . $lesson->getId());
        $this->assertResponseIsSuccessful();

        // --- Complete lesson (POST)
        $this->client->request('POST', '/lesson/' . $lesson->getId() . '/complete');
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Re-fetch & assert in DB
        $this->em->clear();

        $userDb = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
        $lessonDb = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson Dashboard']);
        $this->assertNotNull($userDb);
        $this->assertNotNull($lessonDb);

        $validated = $this->em->getRepository(LessonValidated::class)->findOneBy([
            'user' => $userDb,
            'lesson' => $lessonDb,
        ]);
        $this->assertNotNull($validated);

        $lessonCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'lesson' => $lessonDb,
            'type' => 'lesson',
        ]);
        $this->assertNotNull($lessonCert);
        $this->assertNotEmpty($lessonCert->getCertificateCode());

        // Optionnel: PDF route
        $this->client->request('GET', '/dashboard/certification/' . $lessonCert->getId() . '/pdf');
        $this->assertResponseStatusCodeSame(200);

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertTrue(str_contains($contentType, 'application/pdf'));
    }
}