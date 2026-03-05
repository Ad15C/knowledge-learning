<?php

namespace App\Tests\Admin\Users;

use App\DataFixtures\TestUserFixtures;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminUsersEditTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserRepository $users;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        // Force HTTPS + follow redirects (ton app force https via 301)
        $this->client = static::createClient([], [
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost',
        ]);
        $this->client->followRedirects(true);

        /** @var DatabaseToolCollection $dbTools */
        $dbTools = static::getContainer()->get(DatabaseToolCollection::class);
        $dbTools->get()->loadFixtures([TestUserFixtures::class]);

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->users = static::getContainer()->get(UserRepository::class);
    }

    private function getAdmin(): User
    {
        $admin = $this->users->findOneBy(['email' => TestUserFixtures::ADMIN_EMAIL]);
        self::assertNotNull($admin, 'Admin fixture introuvable.');

        return $admin;
    }

    private function getNormalUser(): User
    {
        $user = $this->users->findOneBy(['email' => TestUserFixtures::USER_EMAIL]);
        self::assertNotNull($user, 'User fixture introuvable.');

        return $user;
    }

    private function assertOnEditPage(int $userId): void
    {
        $uri = $this->client->getRequest()->getUri();
        self::assertStringContainsString("/admin/users/{$userId}/edit", $uri);
        self::assertSelectorTextContains('h1', 'Modifier un client');
    }

    /**
     * Crée un 2e admin "actif" (non archivé) pour éviter le blocage "dernier admin".
     */
    private function createSecondActiveAdmin(): User
    {
        $u = (new User())
            ->setFirstName('Second')
            ->setLastName('Admin')
            ->setEmail('second-admin@example.com')
            ->setPassword('hash');

        $u->setStoredRoles(['ROLE_ADMIN']);
        $u->setArchivedAt(null);

        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    public function test_get_edit_page_as_admin_ok(): void
    {
        $admin = $this->getAdmin();
        $user  = $this->getNormalUser();

        $this->client->loginUser($admin);
        $this->client->request('GET', sprintf('/admin/users/%d/edit', $user->getId()));

        self::assertResponseIsSuccessful();
        $this->assertOnEditPage($user->getId());
    }

    public function test_admin_edits_normal_user_profile_ok(): void
    {
        $admin = $this->getAdmin();
        $user  = $this->getNormalUser();

        $this->client->loginUser($admin);
        $this->client->request('GET', sprintf('/admin/users/%d/edit', $user->getId()));
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer', [
            'user[firstName]' => 'John',
            'user[lastName]'  => 'Doe',
            'user[email]'     => 'john.doe@example.com',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('/admin/users', $this->client->getRequest()->getUri());

        $this->em->clear();
        $updated = $this->users->find($user->getId());
        self::assertSame('John', $updated->getFirstName());
        self::assertSame('Doe', $updated->getLastName());
        self::assertSame('john.doe@example.com', $updated->getEmail());
    }

    public function test_roles_checkbox_check_admin_sets_stored_roles_to_role_admin(): void
    {
        $admin = $this->getAdmin();
        $user  = $this->getNormalUser();

        $this->client->loginUser($admin);
        $this->client->request('GET', sprintf('/admin/users/%d/edit', $user->getId()));
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer', [
            'user[firstName]' => $user->getFirstName(),
            'user[lastName]'  => $user->getLastName(),
            'user[email]'     => $user->getEmail(),
            'user[roles]'     => ['ROLE_ADMIN'],
        ]);

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $updated = $this->users->find($user->getId());
        self::assertSame(['ROLE_ADMIN'], $updated->getStoredRoles());
    }

    public function test_roles_checkbox_uncheck_admin_sets_stored_roles_to_empty_array(): void
    {
        // Il faut 2 admins actifs, sinon le controller refuse (dernier admin)
        $this->createSecondActiveAdmin();

        // Désactive les filtres Doctrine éventuels
        $filters = $this->em->getFilters();
        foreach (array_keys($filters->getEnabledFilters()) as $name) {
            $filters->disable($name);
        }

        $adminEditor = $this->getAdmin();
        $user        = $this->getNormalUser();

        // Prépare le user comme admin
        $user->setStoredRoles(['ROLE_ADMIN']);
        $this->em->flush();
        $this->em->clear();

        $this->client->loginUser($adminEditor);

        $crawler = $this->client->request('GET', sprintf('/admin/users/%d/edit', $user->getId()));
        self::assertResponseIsSuccessful();

        //  Récupère le form
        $form = $crawler->selectButton('Enregistrer')->form();

        // Remplit les champs requis
        $form['user[firstName]'] = $user->getFirstName();
        $form['user[lastName]']  = $user->getLastName();
        $form['user[email]']     = $user->getEmail();

        //  Décocher explicitement "Administrateur"
        // Selon Symfony, ça peut être user[roles][0] ou user[roles]
        if (isset($form['user[roles][0]'])) {
            $form['user[roles][0]']->untick();
        } elseif (isset($form['user[roles]'])) {
            // parfois accessible directement
            $form['user[roles]'] = [];
        } else {
            // fallback : on supprime toute clé roles du submit
            $values = $form->getValues();
            foreach (array_keys($values) as $k) {
                if (str_starts_with($k, 'user[roles]')) {
                    unset($values[$k]);
                }
            }
            $this->client->request($form->getMethod(), $form->getUri(), $values);

            self::assertResponseIsSuccessful();

            $this->em->clear();
            $updated = $this->users->find($user->getId());
            self::assertSame([], $updated->getStoredRoles());
            return;
        }

        //  Submit normal
        $this->client->submit($form);
        self::assertResponseIsSuccessful();

        $this->em->clear();
        $updated = $this->users->find($user->getId());
        self::assertSame([], $updated->getStoredRoles());
    }

    public function test_self_protection_admin_cannot_change_own_roles_even_if_posted(): void
    {
        $admin = $this->getAdmin();
        self::assertSame(['ROLE_ADMIN'], $admin->getStoredRoles());

        $this->client->loginUser($admin);

        $this->client->request('GET', sprintf('/admin/users/%d/edit', $admin->getId()));
        self::assertResponseIsSuccessful();
        $this->assertOnEditPage($admin->getId());

        // POST “malveillant”
        $this->client->request('POST', sprintf('/admin/users/%d/edit', $admin->getId()), [
            'user' => [
                'firstName' => $admin->getFirstName(),
                'lastName'  => $admin->getLastName(),
                'email'     => $admin->getEmail(),
                'roles'     => [], // tentative retrait
            ],
        ]);

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $reloaded = $this->users->find($admin->getId());
        self::assertSame(['ROLE_ADMIN'], $reloaded->getStoredRoles());
    }

    public function test_last_admin_protection_cannot_remove_role_admin_from_last_active_admin(): void
    {
        $admin = $this->getAdmin();
        self::assertSame(1, $this->users->countActiveAdmins());

        $this->client->loginUser($admin);

        // Tentative retrait ROLE_ADMIN
        $this->client->request('POST', sprintf('/admin/users/%d/edit', $admin->getId()), [
            'user' => [
                'firstName' => $admin->getFirstName(),
                'lastName'  => $admin->getLastName(),
                'email'     => $admin->getEmail(),
                'roles'     => [],
            ],
        ]);

        // On suit redirects => on revient sur edit
        self::assertResponseIsSuccessful();
        $this->assertOnEditPage($admin->getId());

        // comportement serveur garanti : roles revert
        $this->em->clear();
        $reloaded = $this->users->find($admin->getId());
        self::assertSame(['ROLE_ADMIN'], $reloaded->getStoredRoles());
    }

    public function test_email_invalid_shows_errors_and_does_not_persist(): void
    {
        $admin = $this->getAdmin();
        $user  = $this->getNormalUser();

        $this->client->loginUser($admin);

        $this->client->request('GET', sprintf('/admin/users/%d/edit', $user->getId()));
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer', [
            'user[firstName]' => $user->getFirstName(),
            'user[lastName]'  => $user->getLastName(),
            'user[email]'     => 'not-an-email',
        ]);

        self::assertResponseIsSuccessful();
        $this->assertOnEditPage($user->getId());

        $this->em->clear();
        $reloaded = $this->users->find($user->getId());
        self::assertSame(TestUserFixtures::USER_EMAIL, $reloaded->getEmail());
    }

    public function test_email_blank_shows_errors_and_does_not_persist(): void
    {
        $admin = $this->getAdmin();
        $user  = $this->getNormalUser();

        $this->client->loginUser($admin);

        $this->client->request('GET', sprintf('/admin/users/%d/edit', $user->getId()));
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer', [
            'user[firstName]' => $user->getFirstName(),
            'user[lastName]'  => $user->getLastName(),
            'user[email]'     => '',
        ]);

        self::assertResponseIsSuccessful();
        $this->assertOnEditPage($user->getId());

        $this->em->clear();
        $reloaded = $this->users->find($user->getId());
        self::assertSame(TestUserFixtures::USER_EMAIL, $reloaded->getEmail());
    }

    public function test_email_duplicate_unique_entity_error(): void
    {
        $admin = $this->getAdmin();
        $user  = $this->getNormalUser();

        $other = (new User())
            ->setFirstName('Other')
            ->setLastName('User')
            ->setEmail('other@example.com')
            ->setPassword('hashed');
        $other->setStoredRoles([]);

        $this->em->persist($other);
        $this->em->flush();
        $otherId = $other->getId();

        $this->client->loginUser($admin);

        $this->client->request('GET', sprintf('/admin/users/%d/edit', $otherId));
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer', [
            'user[firstName]' => 'Other',
            'user[lastName]'  => 'User',
            'user[email]'     => $user->getEmail(),
        ]);

        self::assertResponseIsSuccessful();
        $this->assertOnEditPage($otherId);

        self::assertStringContainsString('Cet email est déjà utilisé', $this->client->getResponse()->getContent() ?? '');

        $this->em->clear();
        $reloaded = $this->users->find($otherId);
        self::assertSame('other@example.com', $reloaded->getEmail());
    }
}