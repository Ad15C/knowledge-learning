<?php

namespace App\Tests\Functional\Admin\Theme;

use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

final class AdminThemeEditTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private RouterInterface $router;

    private User $admin;
    private Theme $theme;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->client->followRedirects(true);

        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->router = $container->get(RouterInterface::class);

        $this->admin = $this->createAdminUser();
        $this->theme = $this->createTheme(
            'Thème édition test',
            'Description existante du thème',
            'images/themes/test/theme.jpg',
            true
        );

        $this->em->flush();
    }

    public function test_edit_page_displays_existing_values(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->router->generate('admin_theme_edit', [
            'id' => $this->theme->getId(),
        ]));

        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();

        self::assertSelectorTextContains('h1.admin-page-title', 'Modifier : '.$this->theme->getName());

        $nameInput = $crawler->filter('input[name="theme[name]"]');
        self::assertCount(1, $nameInput);
        self::assertSame($this->theme->getName(), $nameInput->attr('value'));

        $descriptionTextarea = $crawler->filter('textarea[name="theme[description]"]');
        self::assertCount(1, $descriptionTextarea);
        self::assertSame($this->theme->getDescription(), trim($descriptionTextarea->text()));

        $imageInput = $crawler->filter('input[name="theme[image]"]');
        self::assertCount(1, $imageInput);
        self::assertSame($this->theme->getImage(), $imageInput->attr('value'));
    }

    public function test_edit_page_displays_submit_button(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->router->generate('admin_theme_edit', [
            'id' => $this->theme->getId(),
        ]));

        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();

        $submitButton = $crawler->filter('button[type="submit"]');
        self::assertGreaterThan(0, $submitButton->count());
        self::assertStringContainsString('Enregistrer', trim($submitButton->text()));
    }

    public function test_edit_page_displays_back_link_to_list(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->router->generate('admin_theme_edit', [
            'id' => $this->theme->getId(),
        ]));

        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $indexUrl = $this->router->generate('admin_theme_index');

        self::assertGreaterThan(
            0,
            $crawler->filter('a[href="'.$indexUrl.'"]')->count(),
            'Un lien de retour vers la liste des thèmes doit être présent.'
        );
    }

    public function test_edit_page_displays_validation_errors(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->router->generate('admin_theme_edit', [
            'id' => $this->theme->getId(),
        ]));

        self::assertResponseIsSuccessful();

        $this->client->submitForm('Enregistrer', [
            'theme[name]' => '',
            'theme[description]' => 'Description modifiée',
            'theme[image]' => 'images/themes/test/new-image.jpg',
        ]);

        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $content = $this->client->getResponse()->getContent();

        self::assertIsString($content);
        self::assertStringContainsString('Le nom est obligatoire.', $content);

        $nameInput = $crawler->filter('input[name="theme[name]"]');
        self::assertCount(1, $nameInput);

        $descriptionTextarea = $crawler->filter('textarea[name="theme[description]"]');
        self::assertCount(1, $descriptionTextarea);
        self::assertSame('Description modifiée', trim($descriptionTextarea->text()));

        $imageInput = $crawler->filter('input[name="theme[image]"]');
        self::assertCount(1, $imageInput);
        self::assertSame('images/themes/test/new-image.jpg', $imageInput->attr('value'));
    }

    private function createAdminUser(): User
    {
        $email = 'admin-theme-edit-'.uniqid('', true).'@example.com';

        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Admin')
            ->setLastName('Theme')
            ->setIsVerified(true)
            ->setRoles(['ROLE_ADMIN'])
            ->setCreatedAt(new \DateTimeImmutable('-1 day'))
            ->setPassword('test-password-not-used');

        $this->em->persist($user);

        return $user;
    }

    private function createTheme(
        string $name,
        string $description,
        string $image,
        bool $isActive
    ): Theme {
        $theme = (new Theme())
            ->setName($name)
            ->setDescription($description)
            ->setImage($image)
            ->setIsActive($isActive)
            ->setCreatedAt(new \DateTimeImmutable('2024-01-10 10:00:00'));

        $this->em->persist($theme);

        return $theme;
    }
}