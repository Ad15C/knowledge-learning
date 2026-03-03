<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationFormTypeTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;
    private $databaseTool;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $container = static::getContainer();

        // Reset DB (évite les soucis de FK quand on "DELETE FROM user")
        $this->databaseTool = $container->get(DatabaseToolCollection::class)->get();
        $this->databaseTool->loadFixtures([]); // purge + schema déjà présent

        $this->em = $container->get(EntityManagerInterface::class);
        $this->hasher = $container->get(UserPasswordHasherInterface::class);
    }

    private function createExistingUser(string $email = 'jane@example.com'): User
    {
        $user = new User();
        $user->setEmail($email)
            ->setFirstName('Jane')
            ->setLastName('Doe')
            ->setIsVerified(true);

        // Ton User attend un password hashé (ou au moins non-null)
        $user->setPassword($this->hasher->hashPassword($user, 'Password123!'));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testRegistrationWithValidData(): void
    {
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        // IMPORTANT: le bouton doit matcher ton template
        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]' => 'John',
            'registration_form[lastName]' => 'Doe',
            'registration_form[email]' => 'john.doe@example.com',
            'registration_form[plainPassword][first]' => 'Password123!',
            'registration_form[plainPassword][second]' => 'Password123!',
        ]);

        $this->client->submit($form);

        // selon ton contrôleur c’est souvent /login (ou route app_login)
        self::assertResponseRedirects('/login');

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'john.doe@example.com']);

        self::assertNotNull($user);
        self::assertSame('John', $user->getFirstName());
        self::assertSame('Doe', $user->getLastName());
        self::assertNotEmpty((string) $user->getPassword());
    }

    public function testDuplicateEmailFails(): void
    {
        $this->createExistingUser('jane@example.com');

        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton("S'inscrire")->form([
            'registration_form[firstName]' => 'John',
            'registration_form[lastName]' => 'Doe',
            'registration_form[email]' => 'jane@example.com', // déjà existant
            'registration_form[plainPassword][first]' => 'Password123!',
            'registration_form[plainPassword][second]' => 'Password123!',
        ]);

        $crawler = $this->client->submit($form);

        // pas de redirect => le form est ré-affiché
        self::assertResponseStatusCodeSame(200);

        // Message UniqueEntity défini sur User:
        // #[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
        self::assertSelectorExists('form[name="registration_form"]');
        self::assertStringContainsString(
            'Cet email est déjà utilisé',
            $crawler->filter('form[name="registration_form"]')->text()
        );

        // sécurité: il n'y a qu'un seul user en DB pour cet email
        $users = $this->em->getRepository(User::class)->findBy(['email' => 'jane@example.com']);
        self::assertCount(1, $users);
    }
}