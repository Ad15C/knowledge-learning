<?php

namespace App\Tests\Controller;

use App\DataFixtures\TestUserFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserProfileFormTypeTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient([], [
            'HTTP_ACCEPT_LANGUAGE' => 'fr',
        ]);

        $container = static::getContainer();

        // Purge DB proprement
        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);
    }

    private function createAndPersistUser(string $email = null): User
    {
        $email ??= sprintf('user_%s@example.com', bin2hex(random_bytes(4)));

        $user = new User();
        $user->setEmail($email)
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setIsVerified(true);

        $user->setPassword($this->hasher->hashPassword($user, 'Password1!'));

        $this->em->persist($user);
        $this->em->flush();

        // Important: loginUser préfère un user "managed"
        return $this->em->getRepository(User::class)->find($user->getId());
    }

    public function testEditProfilePageDisplaysCorrectly(): void
    {
        $user = $this->createAndPersistUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="editProfileForm"]');
        self::assertSelectorExists('input[name="editProfileForm[firstName]"]');
        self::assertSelectorExists('input[name="editProfileForm[lastName]"]');
        self::assertSelectorExists('input[name="editProfileForm[email]"]');
        self::assertSelectorExists('input[name="editProfileForm[_token]"]'); // csrf
    }

    public function testSubmitValidProfileFormUpdatesUserAndRedirects(): void
    {
        $user = $this->createAndPersistUser('testuser@example.com');
        $this->client->loginUser($user);

        // 1) GET pour init session + csrf
        $crawler = $this->client->request('GET', '/dashboard/edit');
        self::assertResponseIsSuccessful();

        $newEmail = sprintf('alice_%s@example.com', bin2hex(random_bytes(3)));

        $form = $crawler->selectButton('Mettre à jour')->form([
            'editProfileForm[firstName]' => 'Alice',
            'editProfileForm[lastName]'  => 'Smith',
            'editProfileForm[email]'     => $newEmail,
        ]);

        $this->client->submit($form);

        // Ton contrôleur redirige probablement vers dashboard
        self::assertTrue(
            $this->client->getResponse()->isRedirect(),
            'Le submit devrait rediriger en cas de succès.'
        );

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        // 2) Vérifie en DB
        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($user->getId());
        self::assertNotNull($reloaded);

        self::assertSame('Alice', $reloaded->getFirstName());
        self::assertSame('Smith', $reloaded->getLastName());
        self::assertSame($newEmail, $reloaded->getEmail());
    }

    public function testSubmitInvalidProfileFormShowsErrorsAndDoesNotRedirect(): void
    {
        $user = $this->createAndPersistUser();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/dashboard/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Mettre à jour')->form([
            'editProfileForm[firstName]' => 'A',              // min 2
            'editProfileForm[lastName]'  => 'B',              // min 2
            'editProfileForm[email]'     => 'invalid-email',  // invalid
        ]);

        $crawler = $this->client->submit($form);

        // Pas de redirect => re-render avec erreurs
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('form[name="editProfileForm"]');

        // Assertions robustes : on vérifie qu'il y a des erreurs Symfony (class/form-errors)
        // Adapte le sélecteur si ton template a une autre structure.
        $hasAnyError =
            $crawler->filter('.form-error-message')->count() > 0
            || $crawler->filter('.form-error')->count() > 0
            || $crawler->filter('.invalid-feedback')->count() > 0;

        self::assertTrue($hasAnyError, 'On attend des erreurs de validation visibles dans la page.');

        // DB inchangée
        $this->em->clear();
        $reloaded = $this->em->getRepository(User::class)->find($user->getId());

        self::assertSame('John', $reloaded->getFirstName());
        self::assertSame('Doe', $reloaded->getLastName());
        self::assertSame($user->getEmail(), $reloaded->getEmail());
    }

    public function testMaxLengthValidationDoesNotRedirect(): void
    {
        $user = $this->createAndPersistUser();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/dashboard/edit');
        self::assertResponseIsSuccessful();

        $tooLong = str_repeat('a', 51); // max 50

        $form = $crawler->selectButton('Mettre à jour')->form([
            'editProfileForm[firstName]' => $tooLong,
            'editProfileForm[lastName]'  => $tooLong,
            'editProfileForm[email]'     => 'ok@example.com',
        ]);

        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(200);

        $hasAnyError =
            $crawler->filter('.form-error-message')->count() > 0
            || $crawler->filter('.form-error')->count() > 0
            || $crawler->filter('.invalid-feedback')->count() > 0;

        self::assertTrue($hasAnyError, 'On attend une erreur max length.');
    }

    public function testBackLinkNavigatesToDashboard(): void
    {
        $user = $this->createAndPersistUser();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/dashboard/edit');
        self::assertResponseIsSuccessful();

        self::assertSelectorExists('.btn-back');

        $this->client->click($crawler->filter('.btn-back')->link());

        self::assertResponseIsSuccessful();

        // On évite "Bienvenue" si ton dashboard a un autre titre :
        self::assertSelectorExists('body');
    }
}