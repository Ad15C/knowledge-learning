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

class LessonCompleteCertificationPdfWorkflowTest extends WebTestCase
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

    public function testLessonCompleteCreatesValidationCertificationAndPdf(): void
    {
        // -----------------------------
        // Arrange
        // -----------------------------
        $user = new User();
        $user->setEmail('learner@example.com');
        $user->setFirstName('Learner');
        $user->setLastName('User');
        $user->setRoles([]); // ROLE_USER ajouté automatiquement par getRoles()
        $user->setIsVerified(true);
        $user->setPassword('irrelevant');

        $theme = new Theme();
        $theme->setName('Theme Workflow');
        $theme->setDescription('Theme de test');
        $theme->setIsActive(true);

        $cursus = new Cursus();
        $cursus->setName('Cursus Workflow');
        $cursus->setPrice(19.99);
        $cursus->setDescription('Cursus de test');
        $cursus->setTheme($theme);
        $cursus->setIsActive(true);

        $lesson = new Lesson();
        $lesson->setTitle('Lesson Workflow');
        $lesson->setPrice(9.99);
        $lesson->setCursus($cursus);
        $lesson->setFiche('<p>Contenu pédagogique de test</p>');
        $lesson->setVideoUrl('https://example.test/video');
        $lesson->setIsActive(true);

        $this->em->persist($theme);
        $this->em->persist($cursus);
        $this->em->persist($lesson);
        $this->em->persist($user);
        $this->em->flush();

        // Achat payé donnant accès à la leçon
        $purchase = new Purchase();
        $purchase->setUser($user);
        $purchase->setStatus(Purchase::STATUS_PAID);
        $purchase->setPaidAt(new \DateTimeImmutable());

        $item = new PurchaseItem();
        $item->setLesson($lesson);
        $item->setUnitPrice((float) $lesson->getPrice());
        $item->setQuantity(1);

        $purchase->addItem($item);
        $purchase->calculateTotal();

        $this->em->persist($purchase);
        $this->em->persist($item);
        $this->em->flush();

        // -----------------------------
        // Login
        // -----------------------------
        $this->client->loginUser($user);

        // -----------------------------
        // Page lesson accessible
        // -----------------------------
        $crawler = $this->client->request('GET', $this->router->generate('lesson_show', [
            'id' => $lesson->getId(),
        ]));
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Lesson Workflow');

        $bodyText = $crawler->filter('body')->text();
        $this->assertTrue(
            str_contains($bodyText, 'Marquer la leçon comme complétée')
            || str_contains($bodyText, 'Leçon validée'),
            'La page leçon doit afficher le bouton de complétion ou l’état validé.'
        );

        // -----------------------------
        // Récupération du token CSRF
        // -----------------------------
        $completeUrl = $this->router->generate('lesson_complete', [
            'id' => $lesson->getId(),
        ]);

        $form = $crawler->filter(sprintf('form[action="%s"]', $completeUrl));
        $this->assertGreaterThan(
            0,
            $form->count(),
            'Le formulaire de complétion est introuvable.'
        );

        $tokenNode = $form->filter('input[name="_token"]');
        $this->assertGreaterThan(
            0,
            $tokenNode->count(),
            'Le token CSRF du formulaire de complétion est introuvable.'
        );

        $token = (string) $tokenNode->attr('value');
        $this->assertNotSame('', $token, 'Le token CSRF ne doit pas être vide.');

        // -----------------------------
        // POST complete
        // -----------------------------
        $this->client->request('POST', $completeUrl, [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects(
            $this->router->generate('lesson_show', ['id' => $lesson->getId()])
        );

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('body', 'Leçon validée');
        $this->assertSelectorTextContains('body', 'Une certification a été générée pour cette leçon.');

        // -----------------------------
        // Vérifications DB
        // -----------------------------
        $this->em->clear();

        $userDb = $this->em->getRepository(User::class)->findOneBy([
            'email' => 'learner@example.com',
        ]);
        $this->assertNotNull($userDb);

        $lessonDb = $this->em->getRepository(Lesson::class)->findOneBy([
            'title' => 'Lesson Workflow',
        ]);
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

        // Comme le cursus contient ici une seule leçon,
        // la validation de la leçon génère aussi le certificat cursus.
        $cursusCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'cursus' => $lessonDb->getCursus(),
            'type' => 'cursus',
        ]);
        $this->assertNotNull($cursusCert);

        // Et comme le thème ne contient ici qu’un seul cursus / une seule leçon,
        // le certificat thème est aussi généré.
        $themeCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'theme' => $lessonDb->getCursus()?->getTheme(),
            'type' => 'theme',
        ]);
        $this->assertNotNull($themeCert);

        // -----------------------------
        // PDF
        // -----------------------------
        $this->client->request('GET', $this->router->generate('app_certification_pdf', [
            'id' => $lessonCert->getId(),
        ]));
        $this->assertResponseStatusCodeSame(200);

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType);

        $pdf = $this->client->getResponse()->getContent();
        $this->assertNotFalse($pdf);
        $this->assertStringStartsWith('%PDF', $pdf, 'La réponse doit contenir un vrai PDF.');

        // -----------------------------
        // Rechargement page lesson : le bouton de complétion ne doit plus apparaître
        // -----------------------------
        $this->client->request('GET', $this->router->generate('lesson_show', [
            'id' => $lessonDb->getId(),
        ]));
        $this->assertResponseIsSuccessful();

        $pageTextAfter = $this->client->getCrawler()->filter('body')->text();
        $this->assertTrue(
            str_contains($pageTextAfter, 'Vous avez déjà complété cette leçon.')
            || str_contains($pageTextAfter, 'Leçon validée'),
            'Après validation, la page doit indiquer que la leçon est déjà complétée.'
        );
    }
}