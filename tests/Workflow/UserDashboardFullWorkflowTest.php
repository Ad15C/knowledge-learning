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
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form'); // page login
    }

    public function testFullDashboardWorkflow(): void
    {
        // -----------------------------
        // Arrange: user + purchase paid + certif
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

        // Paid purchase + item => accès aux leçons via tes contrôleurs
        $purchase = new Purchase();
        $purchase->setUser($user);
        $purchase->setStatus('paid');
        $purchase->setPaidAt(new \DateTimeImmutable());

        $item = new PurchaseItem();
        $item->setPurchase($purchase);
        $item->setLesson($lesson);
        $item->setUnitPrice($lesson->getPrice());

        $this->em->persist($purchase);
        $this->em->persist($item);
        $purchase->addItem($item);
        $purchase->calculateTotal();

        // Certification existante (pour tester /dashboard/certifications + show + pdf)
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

        $loginForm = $crawler->selectButton('Se connecter')->form([
            '_username' => 'dash@example.com',
            '_password' => 'OldPass123!',
        ]);
        $this->client->submit($loginForm);

        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect(); // /dashboard
        $this->assertResponseIsSuccessful();

        // -----------------------------
        // 2) Dashboard accessible
        // -----------------------------
        $this->client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();

        // -----------------------------
        // 3) Edit profile (prenom/nom)
        // -----------------------------
        $crawler = $this->client->request('GET', '/dashboard/edit');
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form')->form();

        // Renseigne automatiquement les champs contenant firstName / lastName (robuste)
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

        // Selon ton template, le bouton peut être "Envoyer" ou "Mettre à jour" ou "Changer"
        // On prend le premier bouton submit trouvé si besoin.
        $button = null;
        foreach (['Mettre à jour', 'Envoyer', 'Valider', 'Changer', 'Enregistrer'] as $label) {
            if ($crawler->selectButton($label)->count() > 0) {
                $button = $label;
                break;
            }
        }
        if ($button === null) {
            // fallback : premier submit
            $this->assertGreaterThan(0, $crawler->filter('form button[type="submit"], form input[type="submit"]')->count());
            $form = $crawler->filter('form')->form();
        } else {
            $form = $crawler->selectButton($button)->form();
        }

        // champs probables :
        // change_password_form[plainPassword][first]/[second]
        // ou change_password_form[plainPassword]
        $this->setIfExists($form, 'change_password_form[plainPassword][first]', 'NewPass123!');
        $this->setIfExists($form, 'change_password_form[plainPassword][second]', 'NewPass123!');
        $this->setIfExists($form, 'change_password_form[plainPassword]', 'NewPass123!');
        $this->setIfExists($form, 'change_password[plainPassword][first]', 'NewPass123!');
        $this->setIfExists($form, 'change_password[plainPassword][second]', 'NewPass123!');

        $this->client->submit($form);

        // changePassword() redirect vers dashboard
        $this->assertTrue($this->client->getResponse()->isRedirection());
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Vérif que le nouveau mot de passe fonctionne : on logout + login avec NewPass
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
        // 5) Accès commandes
        // -----------------------------
        $this->client->request('GET', '/dashboard/purchases');
        $this->assertResponseIsSuccessful();

        // Et l'accès direct à une leçon achetée (via lesson_show)
        $lessonDb = $this->em->getRepository(Lesson::class)->findOneBy(['title' => 'Lesson Dash']);
        $this->assertNotNull($lessonDb);

        $this->client->request('GET', '/lesson/' . $lessonDb->getId());
        $this->assertResponseIsSuccessful();

        // -----------------------------
        // 6) Accès certifications + impression PDF
        // -----------------------------
        $this->client->request('GET', '/dashboard/certifications');
        $this->assertResponseIsSuccessful();

        $certDb = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $userDb,
            'certificateCode' => 'KL-TEST-001',
        ]);
        if ($certDb === null) {
            // re-fetch user (au cas où)
            $userDb = $this->em->getRepository(User::class)->findOneBy(['email' => 'dash@example.com']);
            $certDb = $this->em->getRepository(Certification::class)->findOneBy([
                'user' => $userDb,
                'certificateCode' => 'KL-TEST-001',
            ]);
        }
        $this->assertNotNull($certDb);

        // show
        $this->client->request('GET', '/dashboard/certification/' . $certDb->getId());
        $this->assertResponseIsSuccessful();

        // pdf
        $this->client->request('GET', '/dashboard/certification/' . $certDb->getId() . '/pdf');
        $this->assertResponseStatusCodeSame(200);
        $contentType = (string) $this->client->getResponse()->headers->get('Content-Type');
        $this->assertTrue(str_contains($contentType, 'application/pdf'));
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