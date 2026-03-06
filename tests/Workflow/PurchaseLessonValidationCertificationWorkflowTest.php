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

class PurchaseLessonValidationCertificationWorkflowTest extends WebTestCase
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

    public function testWorkflowAccessCompleteGenerateCertificatesAndPdf(): void
    {
        // --- Arrange : user + theme + cursus + lesson
        $user = (new User())
            ->setEmail('buyer@example.com')
            ->setFirstName('Buyer')
            ->setLastName('User')
            ->setRoles([]) // ROLE_USER est ajouté automatiquement par getRoles()
            ->setIsVerified(true)
            ->setPassword('irrelevant');

        $theme = (new Theme())
            ->setName('Theme Test')
            ->setDescription('Theme de test')
            ->setIsActive(true);

        $cursus = (new Cursus())
            ->setName('Cursus Test')
            ->setPrice(49.99)
            ->setDescription('Cursus de test')
            ->setTheme($theme)
            ->setIsActive(true);

        $lesson = (new Lesson())
            ->setTitle('Lesson 1')
            ->setPrice(9.99)
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
            ->setUnitPrice(9.99)
            ->setQuantity(1);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->persist($item);
        $this->em->flush();

        // --- Login
        $this->client->loginUser($user);

        // --- Dashboard user
        $this->client->request('GET', $this->router->generate('user_dashboard'));
        $this->assertResponseIsSuccessful();

        // --- Accès à la page leçon
        $lessonCrawler = $this->client->request('GET', $this->router->generate('lesson_show', [
            'id' => $lesson->getId(),
        ]));
        $this->assertResponseIsSuccessful();

        // Vérifie que le formulaire de complétion est présent
        $completeForm = $lessonCrawler->filter(sprintf(
            'form[action="%s"]',
            $this->router->generate('lesson_complete', ['id' => $lesson->getId()])
        ));
        $this->assertGreaterThan(
            0,
            $completeForm->count(),
            'Formulaire de complétion introuvable sur la page leçon.'
        );

        // --- Récupération du CSRF token
        $completeTokenNode = $completeForm->filter('input[name="_token"]');
        $this->assertGreaterThan(
            0,
            $completeTokenNode->count(),
            'Token CSRF introuvable dans le formulaire de complétion.'
        );

        $completeToken = (string) $completeTokenNode->attr('value');
        $this->assertNotSame('', $completeToken, 'Token CSRF vide.');

        // --- Complétion de la leçon
        $this->client->request('POST', $this->router->generate('lesson_complete', [
            'id' => $lesson->getId(),
        ]), [
            '_token' => $completeToken,
        ]);

        $this->assertResponseRedirects(
            $this->router->generate('lesson_show', ['id' => $lesson->getId()])
        );

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // --- Vérifications DB
        $this->em->clear();

        $userDb = $this->em->getRepository(User::class)->findOneBy([
            'email' => 'buyer@example.com',
        ]);
        $this->assertNotNull($userDb);

        $lessonDb = $this->em->getRepository(Lesson::class)->findOneBy([
            'title' => 'Lesson 1',
        ]);
        $this->assertNotNull($lessonDb);

        $cursusDb = $lessonDb->getCursus();
        $this->assertNotNull($cursusDb);

        $paidPurchase = $this->em->getRepository(Purchase::class)->findOneBy([
            'user' => $userDb,
            'status' => Purchase::STATUS_PAID,
        ]);
        $this->assertNotNull($paidPurchase);
        $this->assertNotNull($paidPurchase->getPaidAt());

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

        // Comme le cursus ne contient qu'une seule leçon,
        // la validation de cette leçon termine aussi le cursus.
        $cursusCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'cursus' => $cursusDb,
            'type' => 'cursus',
        ]);
        $this->assertNotNull($cursusCert);

        // Comme ce thème ne contient ici qu'un seul cursus avec une seule leçon,
        // la validation termine aussi le thème.
        $themeCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'theme' => $cursusDb->getTheme(),
            'type' => 'theme',
        ]);
        $this->assertNotNull($themeCert);

        // --- Route PDF
        $this->client->request('GET', $this->router->generate('app_certification_pdf', [
            'id' => $lessonCert->getId(),
        ]));
        $this->assertResponseStatusCodeSame(200);

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType);
    }
}