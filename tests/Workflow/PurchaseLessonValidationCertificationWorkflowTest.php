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
        // --- Arrange
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
        $this->client->loginUser($user);

        // --- Open cursus page and find ANY "add lesson to cart" form
        $cursusCrawler = $this->client->request('GET', '/cursus/' . $cursus->getId());
        $this->assertResponseIsSuccessful();

        $cursusCrawler = $this->client->request('GET', '/cursus/' . $cursus->getId());
        $this->assertResponseIsSuccessful();

        // Sélecteur robuste : n'importe quel form qui poste vers /cart/add/lesson/{id}
        $forms = $cursusCrawler->filter('form[action^="/cart/add/lesson/"]');
        $this->assertGreaterThan(0, $forms->count(), 'Aucun formulaire add lesson trouvé sur /cursus/{id}.');

        $firstForm = $forms->first();
        $action = (string) $firstForm->attr('action');

        $tokenNode = $firstForm->filter('input[name="_token"]');
        $this->assertGreaterThan(0, $tokenNode->count(), 'Champ _token introuvable dans le form add lesson.');
        $token = (string) $tokenNode->attr('value');
        $this->assertNotSame('', $token);

        // --- POST add-to-cart
        $this->client->request('POST', $action, [
            '_token' => $token,
        ]);
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // --- Cart purchase exists
        $userDb = $this->em->getRepository(User::class)->findOneBy(['email' => 'buyer@example.com']);
        $this->assertNotNull($userDb);

        $cartPurchase = $this->em->getRepository(Purchase::class)->findOneBy([
            'user' => $userDb,
            'status' => 'cart',
        ]);
        $this->assertNotNull($cartPurchase);
        $this->assertGreaterThanOrEqual(1, $cartPurchase->getItems()->count());

        // --- Pay from /cart (CSRF from form)
        $cartCrawler = $this->client->request('GET', '/cart');
        $this->assertResponseIsSuccessful();

        $payTokenNode = $cartCrawler->filter('form[action$="/cart/pay"] input[name="_token"]');
        $this->assertGreaterThan(0, $payTokenNode->count(), 'Token CSRF introuvable dans le form /cart/pay.');
        $payToken = (string) $payTokenNode->attr('value');
        $this->assertNotSame('', $payToken);

        $this->client->request('POST', '/cart/pay', [
            '_token' => $payToken,
        ]);

        $this->assertTrue($this->client->getResponse()->isRedirection(), 'Paiement doit rediriger.');
        $successUrl = (string) $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('/cart/success/', $successUrl);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // --- Re-fetch
        $this->em->clear();

        $userDb = $this->em->getRepository(User::class)->findOneBy(['email' => 'buyer@example.com']);
        $lessonDb = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson 1']);
        $cursusDb = $lessonDb->getCursus();

        $paidPurchase = $this->em->getRepository(Purchase::class)->findOneBy([
            'user' => $userDb,
            'status' => 'paid',
        ]);
        $this->assertNotNull($paidPurchase);
        $this->assertNotNull($paidPurchase->getPaidAt());

        // --- Access lesson page and grab completion CSRF token
        $lessonCrawler = $this->client->request('GET', '/lesson/' . $lessonDb->getId());
        $this->assertResponseIsSuccessful();

        $completeForm = $lessonCrawler->filter(sprintf('form[action$="/lesson/%d/complete"]', $lessonDb->getId()));
        $this->assertGreaterThan(0, $completeForm->count(), 'Formulaire de complétion introuvable sur la page leçon.');

        $completeTokenNode = $completeForm->filter('input[name="_token"]');
        $this->assertGreaterThan(0, $completeTokenNode->count(), 'Token CSRF introuvable dans le form de complétion.');
        $completeToken = (string) $completeTokenNode->attr('value');
        $this->assertNotSame('', $completeToken);

        $this->client->request('POST', '/lesson/' . $lessonDb->getId() . '/complete', [
            '_token' => $completeToken,
        ]);
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // --- Assertions DB
        $this->em->clear();

        $userDb = $this->em->getRepository(User::class)->findOneBy(['email' => 'buyer@example.com']);
        $lessonDb = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson 1']);
        $cursusDb = $lessonDb->getCursus();

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

        $cursusCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'cursus' => $cursusDb,
            'type' => 'cursus',
        ]);
        $this->assertNotNull($cursusCert);

        // --- PDF route
        $this->client->request('GET', '/dashboard/certification/' . $lessonCert->getId() . '/pdf');
        $this->assertResponseStatusCodeSame(200);

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType);
    }
}