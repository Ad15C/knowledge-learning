<?php

namespace App\Tests\Workflow;

use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Entity\Theme;
use App\Entity\User;
use App\Tests\DoctrineSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserDashboardFullWorkflowTest extends WebTestCase
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

    public function testDashboardIsProtectedWhenNotLoggedIn(): void
    {
        $this->client->request('GET', '/dashboard');
        $this->assertTrue($this->client->getResponse()->isRedirection());

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('form');
        $this->assertGreaterThan(
            0,
            $crawler->selectButton('Se connecter')->count(),
            'Le bouton "Se connecter" doit exister sur la page login.'
        );
    }

    public function testFullDashboardWorkflowWithFullAssertions(): void
    {
        // -----------------------------
        // Arrange
        // -----------------------------
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('dash@example.com');
        $user->setFirstName('OldFirst');
        $user->setLastName('OldLast');
        $user->setRoles([]); // ROLE_USER est ajouté automatiquement par getRoles()
        $user->setIsVerified(true);
        $user->setPassword($passwordHasher->hashPassword($user, 'OldPass123!'));

        $theme = new Theme();
        $theme->setName('Theme Dash');
        $theme->setDescription('Theme de test');
        $theme->setIsActive(true);

        $cursus = new Cursus();
        $cursus->setName('Cursus Dash');
        $cursus->setPrice(49.99);
        $cursus->setDescription('Cursus de test');
        $cursus->setTheme($theme);
        $cursus->setIsActive(true);

        $lesson = new Lesson();
        $lesson->setTitle('Lesson Dash');
        $lesson->setPrice(9.99);
        $lesson->setCursus($cursus);
        $lesson->setFiche('<p>Contenu de test</p>');
        $lesson->setVideoUrl('https://example.test/video');
        $lesson->setIsActive(true);

        $this->em->persist($theme);
        $this->em->persist($cursus);
        $this->em->persist($lesson);
        $this->em->persist($user);
        $this->em->flush();

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

        $cert = new Certification();
        $cert->setUser($user);
        $cert->setLesson($lesson);
        $cert->setType('lesson');
        $cert->setCertificateCode('KL-TEST-001');
        $cert->setIssuedAt(new \DateTimeImmutable());

        $this->em->persist($cert);
        $this->em->flush();

        // -----------------------------
        // 1) Login
        // -----------------------------
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $this->assertHeaderMenuContains(['Accueil', 'Thèmes', 'S\'inscrire', 'Se connecter']);

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'dash@example.com',
            '_password' => 'OldPass123!',
        ]);
        $this->client->submit($loginForm);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->assertHeaderMenuContains(['Accueil', 'Thèmes', 'Panier', 'Dashboard User', 'Contact', 'Déconnexion']);

        $this->assertSelectorExists('.dashboard-layout');
        $this->assertSelectorExists('.dashboard-sidebar');
        $this->assertSelectorTextContains('.sidebar-header', 'Espace membre');

        // -----------------------------
        // 2) Dashboard
        // -----------------------------
        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('h1');
        $this->assertSelectorExists('.dashboard-cards');
        $this->assertSelectorExists('a[href="/dashboard/purchases"]');
        $this->assertSelectorExists('a[href="/dashboard/certifications"]');

        // -----------------------------
        // 3) Edit profile
        // -----------------------------
        $crawler = $this->client->request('GET', '/dashboard/edit');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');

        $form = $crawler->filter('form')->form();

        $this->assertFormHasField($form, 'editProfileForm[firstName]');
        $this->assertFormHasField($form, 'editProfileForm[lastName]');
        $this->assertFormHasField($form, 'editProfileForm[email]');

        $form['editProfileForm[firstName]'] = 'NewFirst';
        $form['editProfileForm[lastName]'] = 'NewLast';
        $form['editProfileForm[email]'] = 'dash@example.com';

        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->em->clear();

        $userDb = $this->em->getRepository(User::class)->findOneBy(['email' => 'dash@example.com']);
        $this->assertNotNull($userDb);
        $this->assertSame('NewFirst', $userDb->getFirstName());
        $this->assertSame('NewLast', $userDb->getLastName());

        // -----------------------------
        // 4) Change password
        // -----------------------------
        $crawler = $this->client->request('GET', '/dashboard/password');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');

        $form = $crawler->filter('form')->form();

        $this->assertFormHasField($form, 'change_password[plainPassword][first]');
        $this->assertFormHasField($form, 'change_password[plainPassword][second]');

        $form['change_password[plainPassword][first]'] = 'NewPass123!';
        $form['change_password[plainPassword][second]'] = 'NewPass123!';

        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->em->clear();
        $userDb = $this->em->getRepository(User::class)->findOneBy(['email' => 'dash@example.com']);
        $this->assertNotNull($userDb);
        $this->assertTrue($passwordHasher->isPasswordValid($userDb, 'NewPass123!'));

        // Nouveau client pour vérifier le vrai login avec le nouveau mot de passe
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->client->disableReboot();

        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'dash@example.com',
            '_password' => 'NewPass123!',
        ]);
        $this->client->submit($loginForm);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // -----------------------------
        // 5) Purchases page + access lesson
        // -----------------------------
        $crawler = $this->client->request('GET', '/dashboard/purchases');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('form.dashboard-filters');
        $this->assertSelectorTextContains('body', 'Commande #');
        $this->assertSelectorTextContains('body', 'Lesson Dash');

        $lessonDb = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson Dash']);
        $this->assertNotNull($lessonDb);

        $crawler = $this->client->request('GET', '/lesson/' . $lessonDb->getId());
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Lesson Dash');

        $pageText = $crawler->filter('body')->text();
        $this->assertTrue(
            str_contains($pageText, 'Marquer la leçon comme complétée')
            || str_contains($pageText, 'déjà complété')
            || str_contains($pageText, 'déjà complétée'),
            'La page leçon doit afficher le bouton de complétion ou indiquer que la leçon est déjà complétée.'
        );

        // -----------------------------
        // 6) Certifications list + show + pdf
        // -----------------------------
        $this->em->clear();
        $userDb = $this->em->getRepository(User::class)->findOneBy(['email' => 'dash@example.com']);
        $this->assertNotNull($userDb);

        $crawler = $this->client->request('GET', '/dashboard/certifications');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('form.dashboard-filters');
        $this->assertSelectorExists('select#cursus');
        $this->assertSelectorExists('input#from');
        $this->assertSelectorExists('input#to');
        $this->assertSelectorTextContains('body', 'KL-TEST-001');

        $certDb = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'certificateCode' => 'KL-TEST-001',
        ]);
        $this->assertNotNull($certDb);

        $crawler = $this->client->request('GET', '/dashboard/certification/' . $certDb->getId());
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('body', 'CERTIFICAT OFFICIEL');
        $this->assertSelectorTextContains('body', 'NewFirst');
        $this->assertSelectorTextContains('body', 'NewLast');
        $this->assertSelectorTextContains('body', 'KL-TEST-001');

        $this->assertSelectorExists('a[href="/dashboard/certification/' . $certDb->getId() . '/pdf"]');

        $this->client->request('GET', '/dashboard/certification/' . $certDb->getId() . '/pdf');
        $this->assertResponseStatusCodeSame(200);

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertStringContainsString('application/pdf', $contentType);

        $pdf = $this->client->getResponse()->getContent();
        $this->assertNotFalse($pdf);
        $this->assertStringStartsWith('%PDF', $pdf, 'Le contenu retourné doit être un PDF.');

        // -----------------------------
        // 7) Bonus: pages dashboard accessibles
        // -----------------------------
        foreach ([
            '/dashboard',
            '/dashboard/edit',
            '/dashboard/password',
            '/dashboard/purchases',
            '/dashboard/certifications',
        ] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful(sprintf(
                'La page %s doit être accessible une fois connecté.',
                $url
            ));
        }
    }

    private function assertHeaderMenuContains(array $labels): void
    {
        $crawler = $this->client->getCrawler();

        $this->assertSelectorExists('header.main-header');
        $this->assertSelectorExists('nav.main-nav');

        $text = $crawler->filter('header.main-header')->text();

        foreach ($labels as $label) {
            $this->assertTrue(
                str_contains($text, $label),
                sprintf('Le header doit contenir "%s".', $label)
            );
        }
    }

    private function assertFormHasField($form, string $fieldName): void
    {
        try {
            $field = $form[$fieldName];
            $this->assertNotNull($field);
        } catch (\Throwable $e) {
            $this->fail(sprintf('Le champ "%s" est introuvable dans le formulaire.', $fieldName));
        }
    }
}