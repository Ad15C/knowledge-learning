<?php

namespace App\Tests\Workflow;

use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\Theme;
use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class DashboardLessonValidationCertificationWorkflowTest extends WebTestCase
{
    use DoctrineSchemaTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UrlGeneratorInterface $router;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = self::createClient();
        $this->client->disableReboot();

        $container = self::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->router = $container->get('router');

        $this->resetDatabaseSchema($this->em);
    }

    public function testDashboardToLessonCompleteGeneratesCertification(): void
    {
        // --- Arrange : user + theme + cursus + lesson
        $user = (new User())
            ->setEmail('user@example.com')
            ->setFirstName('User')
            ->setLastName('Test')
            ->setRoles([]) // ROLE_USER ajouté automatiquement par getRoles()
            ->setIsVerified(true)
            ->setPassword('irrelevant');

        $theme = (new Theme())
            ->setName('Theme Dashboard')
            ->setDescription('Theme de test')
            ->setIsActive(true);

        $cursus = (new Cursus())
            ->setName('Cursus Dashboard')
            ->setPrice(10.00)
            ->setDescription('Cursus de test')
            ->setTheme($theme)
            ->setIsActive(true);

        $lesson = (new Lesson())
            ->setTitle('Lesson Dashboard')
            ->setPrice(0.00)
            ->setCursus($cursus)
            ->setFiche('<p>Contenu de test</p>')
            ->setVideoUrl('https://example.test/video')
            ->setIsActive(true);

        $this->em->persist($theme);
        $this->em->persist($cursus);
        $this->em->persist($lesson);
        $this->em->persist($user);
        $this->em->flush();

        // --- Achat payé donnant accès à la leçon
        $purchase = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_PAID)
            ->setPaidAt(new \DateTimeImmutable());

        $item = (new PurchaseItem())
            ->setPurchase($purchase)
            ->setLesson($lesson)
            ->setUnitPrice(0.00)
            ->setQuantity(1);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->persist($item);
        $this->em->flush();

        // --- Login
        $this->client->loginUser($user);

        // --- Dashboard OK
        $this->client->request('GET', $this->router->generate('user_dashboard'));
        $this->assertResponseIsSuccessful();

        // --- Open lesson page
        $crawler = $this->client->request('GET', $this->router->generate('lesson_show', [
            'id' => $lesson->getId(),
        ]));
        $this->assertResponseIsSuccessful();

        // --- Récupère le CSRF token du formulaire
        $tokenNode = $crawler->filter('form input[name="_token"]');
        $this->assertGreaterThan(
            0,
            $tokenNode->count(),
            'Token CSRF introuvable dans le formulaire de validation de leçon.'
        );

        $tokenValue = (string) $tokenNode->attr('value');
        $this->assertNotSame('', $tokenValue, 'Token CSRF vide.');

        // --- Complete lesson
        $this->client->request('POST', $this->router->generate('lesson_complete', [
            'id' => $lesson->getId(),
        ]), [
            '_token' => $tokenValue,
        ]);

        $this->assertResponseRedirects(
            $this->router->generate('lesson_show', ['id' => $lesson->getId()])
        );

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // --- Re-fetch & assert in DB
        $this->em->clear();

        $userDb = $this->em->getRepository(User::class)->findOneBy([
            'email' => 'user@example.com',
        ]);
        $lessonDb = $this->em->getRepository(Lesson::class)->findOneBy([
            'title' => 'Lesson Dashboard',
        ]);

        $this->assertNotNull($userDb);
        $this->assertNotNull($lessonDb);

        $validated = $this->em->getRepository(LessonValidated::class)->findOneBy([
            'user' => $userDb,
            'lesson' => $lessonDb,
        ]);

        $this->assertNotNull($validated);
        $this->assertTrue($validated->isCompleted());
        $this->assertNotNull($validated->getValidatedAt());

        $lessonCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'lesson' => $lessonDb,
            'type' => 'lesson',
        ]);

        $this->assertNotNull($lessonCert);
        $this->assertNotEmpty($lessonCert->getCertificateCode());
        $this->assertNotNull($lessonCert->getIssuedAt());

        // --- PDF route
        $this->client->request('GET', $this->router->generate('app_certification_pdf', [
            'id' => $lessonCert->getId(),
        ]));
        $this->assertResponseStatusCodeSame(200);

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType);
    }
}