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
        // --- Arrange: user + theme + cursus + lesson
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setFirstName('User');
        $user->setLastName('Test');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);
        // Pas besoin d'un vrai hash si on utilise loginUser()
        $user->setPassword('irrelevant');

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

        // IMPORTANT: LessonController protégé et vérifie l'accès via PurchaseItem(status=paid)
        $purchase = new \App\Entity\Purchase();
        $purchase->setUser($user);
        $purchase->setStatus('paid');
        $purchase->setPaidAt(new \DateTimeImmutable());

        $item = new \App\Entity\PurchaseItem();
        $item->setPurchase($purchase);
        $item->setLesson($lesson);
        $item->setUnitPrice(0.00);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->persist($item);
        $this->em->flush();

        // --- Login (stable, ne dépend pas du formulaire / champs)
        $this->client->loginUser($user);

        // --- Dashboard OK
        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        // --- Open lesson page (should be accessible)
        $crawler = $this->client->request('GET', '/lesson/' . $lesson->getId());
        $this->assertResponseIsSuccessful();

        // --- Récupère le CSRF token depuis le formulaire rendu (robuste + session OK)
        // Le formulaire Twig:
        // <input type="hidden" name="_token" value="{{ csrf_token('lesson_complete_' ~ lesson.id) }}">
        $tokenNode = $crawler->filter('form input[name="_token"]');
        $this->assertGreaterThan(
            0,
            $tokenNode->count(),
            'Token CSRF introuvable dans le formulaire de validation de leçon.'
        );
        $tokenValue = (string) $tokenNode->attr('value');
        $this->assertNotSame('', $tokenValue, 'Token CSRF vide.');

        // --- Complete lesson (POST) avec CSRF
        $this->client->request('POST', '/lesson/' . $lesson->getId() . '/complete', [
            '_token' => $tokenValue,
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // --- Re-fetch & assert in DB
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

        // --- PDF route
        $this->client->request('GET', '/dashboard/certification/' . $lessonCert->getId() . '/pdf');
        $this->assertResponseStatusCodeSame(200);

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType);
    }
}