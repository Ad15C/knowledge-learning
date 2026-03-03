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

        // Page login : form + bouton "Se connecter"
        $this->assertSelectorExists('form');
        $this->assertGreaterThan(
            0,
            $crawler->selectButton('Se connecter')->count(),
            'Le bouton "Se connecter" doit exister sur la page login'
        );
    }

    public function testFullDashboardWorkflowWithFullAssertions(): void
    {
        // -----------------------------
        // Arrange: user + theme/cursus/lesson + purchase paid + certif
        // -----------------------------
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('dash@example.com');
        $user->setFirstName('OldFirst');
        $user->setLastName('OldLast');
        $user->setRoles(['ROLE_USER']);
        $user->setIsVerified(true);
        $user->setPassword($passwordHasher->hashPassword($user, 'OldPass123!'));

        $theme = new Theme();
        $theme->setName('Theme Dash');

        $cursus = new Cursus();
        $cursus->setName('Cursus Dash');
        $cursus->setPrice(49.99);
        $cursus->setTheme($theme);

        $lesson = new Lesson();
        $lesson->setTitle('Lesson Dash');
        $lesson->setPrice(9.99);
        $lesson->setCursus($cursus);

        $this->em->persist($theme);
        $this->em->persist($cursus);
        $this->em->persist($lesson);
        $this->em->persist($user);
        $this->em->flush();

        // Purchase paid + item
        $purchase = new Purchase();
        $purchase->setUser($user);
        $purchase->setStatus('paid');
        $purchase->setPaidAt(new \DateTimeImmutable());

        $item = new PurchaseItem();
        $item->setPurchase($purchase);
        $item->setLesson($lesson);
        $item->setUnitPrice($lesson->getPrice());
        // si tu as quantity obligatoire côté entité:
        if (method_exists($item, 'setQuantity')) {
            $item->setQuantity(1);
        }

        $this->em->persist($purchase);
        $this->em->persist($item);
        $purchase->addItem($item);

        // si ta méthode existe
        if (method_exists($purchase, 'calculateTotal')) {
            $purchase->calculateTotal();
        }

        // Certification existante
        $cert = new Certification();
        $cert->setUser($user);
        $cert->setLesson($lesson);
        $cert->setType('lesson');
        $cert->setCertificateCode('KL-TEST-001');
        $cert->setIssuedAt(new \DateTime());

        $this->em->persist($cert);
        $this->em->flush();

        // -----------------------------
        // 1) Login
        // -----------------------------
        $crawler = $this->client->request('GET', '/login');
        $this->assertResponseIsSuccessful();

        // Vérifie header visiteur (avant login) : Accueil / Thèmes / S'inscrire / Se connecter
        $this->assertHeaderMenuContains(['Accueil', 'Thèmes', 'S\'inscrire', 'Se connecter']);

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'dash@example.com',
            '_password' => 'OldPass123!',
        ]);
        $this->client->submit($loginForm);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $crawler = $this->client->followRedirect(); // /dashboard
        $this->assertResponseIsSuccessful();

        // Après login : header user (non admin) => Accueil/Thèmes/Panier/Dashboard User/Contact/Déconnexion
        $this->assertHeaderMenuContains(['Accueil', 'Thèmes', 'Panier', 'Dashboard User', 'Contact', 'Déconnexion']);

        // Sidebar dashboard existe
        $this->assertSelectorExists('.dashboard-layout');
        $this->assertSelectorExists('.dashboard-sidebar');
        $this->assertSelectorTextContains('.sidebar-header', 'Espace membre');

        // -----------------------------
        // 2) Dashboard content (home)
        // -----------------------------
        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        // "Bonjour, ..." + cards
        $this->assertSelectorExists('h1');
        $this->assertSelectorExists('.dashboard-cards');

        // Les liens cards attendus existent (au moins)
        $this->assertSelectorExists('a[href="/dashboard/purchases"]');
        $this->assertSelectorExists('a[href="/dashboard/certifications"]');

        // -----------------------------
        // 3) Edit profile
        // -----------------------------
        $crawler = $this->client->request('GET', '/dashboard/edit');
        $this->assertResponseIsSuccessful();

        // page contient le container de form générique (selon ton include)
        $this->assertSelectorExists('form');

        $form = $crawler->filter('form')->form();

        // robust: trouve first/last
        $values = $form->getValues();
        $firstKey = null;
        $lastKey  = null;

        foreach (array_keys($values) as $key) {
            if ($firstKey === null && stripos($key, 'first') !== false) {
                $firstKey = $key;
            }
            if ($lastKey === null && stripos($key, 'last') !== false) {
                $lastKey = $key;
            }
        }

        $this->assertNotNull($firstKey, 'Champ firstName introuvable dans le formulaire /dashboard/edit');
        $this->assertNotNull($lastKey, 'Champ lastName introuvable dans le formulaire /dashboard/edit');

        $form[$firstKey] = 'NewFirst';
        $form[$lastKey]  = 'NewLast';

        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Vérif DB
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

        $form = $this->findFirstSubmitForm($crawler);

        // Champs probables
        $this->setIfExists($form, 'change_password_form[plainPassword][first]', 'NewPass123!');
        $this->setIfExists($form, 'change_password_form[plainPassword][second]', 'NewPass123!');
        $this->setIfExists($form, 'change_password_form[plainPassword]', 'NewPass123!');
        $this->setIfExists($form, 'change_password[plainPassword][first]', 'NewPass123!');
        $this->setIfExists($form, 'change_password[plainPassword][second]', 'NewPass123!');
        $this->setIfExists($form, 'change_password[plainPassword]', 'NewPass123!');

        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Logout + relogin avec new pass
        $this->client->request('GET', '/logout');
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

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

        // Vérifs génériques : au minimum un contenu listant une commande / achat
        // (selon template, adapte les sélecteurs si tu as une table ou cards)
        $this->assertTrue(
            $crawler->filter('body')->text() !== '',
            'La page purchases ne doit pas être vide'
        );

        // Accès direct à la leçon achetée
        $lessonDb = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson Dash']);
        $this->assertNotNull($lessonDb);

        $crawler = $this->client->request('GET', '/lesson/' . $lessonDb->getId());
        $this->assertResponseIsSuccessful();

        // Page lesson contient le titre
        $this->assertSelectorTextContains('h1', 'Lesson Dash');

        // Avec achat paid item lesson, l'utilisateur devrait avoir accès :
        // soit bouton "Marquer comme complétée" (si pas complétée),
        // soit texte "déjà complété"
        $pageText = $crawler->filter('body')->text();
        $this->assertTrue(
            str_contains($pageText, 'Marquer comme complétée')
            || str_contains($pageText, 'déjà complété')
            || str_contains($pageText, 'déjà complétée'),
            'La page leçon doit afficher le bouton de complétion ou indiquer que la leçon est déjà complétée'
        );

        // -----------------------------
        // 6) Certifications list + show + filters + pdf
        // -----------------------------
        $crawler = $this->client->request('GET', '/dashboard/certifications');
        $this->assertResponseIsSuccessful();

        // Form de filtres
        $this->assertSelectorExists('form.dashboard-filters');
        $this->assertSelectorExists('select#cursus');
        $this->assertSelectorExists('input#from');
        $this->assertSelectorExists('input#to');

        // La certif créée doit apparaître (code)
        $this->assertSelectorTextContains('body', 'KL-TEST-001');

        $certDb = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'certificateCode' => 'KL-TEST-001',
        ]);
        $this->assertNotNull($certDb);

        // show
        $crawler = $this->client->request('GET', '/dashboard/certification/' . $certDb->getId());
        $this->assertResponseIsSuccessful();

        // "CERTIFICAT OFFICIEL" + identité user
        $this->assertSelectorTextContains('body', 'CERTIFICAT OFFICIEL');
        $this->assertSelectorTextContains('body', 'NewFirst');
        $this->assertSelectorTextContains('body', 'NewLast');
        $this->assertSelectorTextContains('body', 'KL-TEST-001');

        // lien PDF présent sur la page show
        $this->assertSelectorExists('a[href="/dashboard/certification/' . $certDb->getId() . '/pdf"]');

        // pdf
        $this->client->request('GET', '/dashboard/certification/' . $certDb->getId() . '/pdf');
        $this->assertResponseStatusCodeSame(200);

        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertTrue(str_contains($contentType, 'application/pdf'), 'Le Content-Type doit être application/pdf');

        $pdf = $this->client->getResponse()->getContent();
        $this->assertNotFalse($pdf);
        $this->assertStringStartsWith('%PDF', $pdf, 'Le contenu retourné doit être un PDF');

        // -----------------------------
        // 7) Bonus : vérifie que l'accès aux pages dashboard est bien autorisé (pas de redirect)
        // -----------------------------
        foreach ([
            '/dashboard',
            '/dashboard/edit',
            '/dashboard/password',
            '/dashboard/purchases',
            '/dashboard/certifications',
        ] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful(sprintf('La page %s doit être accessible une fois connecté', $url));
        }
    }

    /**
     * Vérifie que les labels apparaissent dans le header menu (sans être trop strict sur la structure HTML).
     */
    private function assertHeaderMenuContains(array $labels): void
    {
        $crawler = $this->client->getCrawler();
        $this->assertSelectorExists('header.main-header');
        $this->assertSelectorExists('nav.main-nav');

        $text = $crawler->filter('header.main-header')->text();

        foreach ($labels as $label) {
            $this->assertTrue(
                str_contains($text, $label),
                sprintf('Le header doit contenir "%s"', $label)
            );
        }
    }

    /**
     * Trouve le formulaire associé au premier bouton submit "connu", sinon le premier <form>.
     */
    private function findFirstSubmitForm($crawler)
    {
        foreach (['Mettre à jour', 'Envoyer', 'Valider', 'Changer', 'Enregistrer', 'Modifier le mot de passe'] as $label) {
            if ($crawler->selectButton($label)->count() > 0) {
                return $crawler->selectButton($label)->form();
            }
        }

        // fallback : premier form
        $this->assertGreaterThan(0, $crawler->filter('form')->count(), 'Aucun <form> trouvé sur la page');
        return $crawler->filter('form')->form();
    }

    /**
     * Définit une valeur si le champ existe dans le Form (robuste aux noms).
     */
    private function setIfExists($form, string $field, string $value): void
    {
        try {
            if (isset($form[$field])) {
                $form[$field] = $value;
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}