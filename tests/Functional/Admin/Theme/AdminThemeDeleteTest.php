<?php

namespace App\Tests\Functional\Admin\Theme;

use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

final class AdminThemeDeleteTest extends WebTestCase
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
            'Thème suppression test',
            'Description du thème à désactiver',
            'images/themes/test/delete.jpg',
            true
        );

        $this->em->flush();
    }

    public function test_delete_confirm_page_displays_confirmation_content(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->router->generate('admin_theme_delete', [
            'id' => $this->theme->getId(),
        ]));

        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $content = $this->client->getResponse()->getContent();

        self::assertSelectorTextContains('h1.admin-page-title', 'Désactiver un thème');
        self::assertSelectorTextContains('.theme-detail-title', $this->theme->getName());

        self::assertIsString($content);
        self::assertStringContainsString($this->theme->getName(), $content);
        self::assertStringContainsString((string) $this->theme->getId(), $this->router->generate('admin_theme_disable', [
            'id' => $this->theme->getId(),
        ]));

        $desc = $crawler->filter('.theme-detail-desc');
        self::assertGreaterThan(0, $desc->count());
        self::assertStringContainsString($this->theme->getDescription(), $desc->text());
    }

    public function test_confirm_button_posts_to_disable_route_with_csrf_token(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->router->generate('admin_theme_delete', [
            'id' => $this->theme->getId(),
        ]));

        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $disableUrl = $this->router->generate('admin_theme_disable', ['id' => $this->theme->getId()]);

        $form = $crawler->filter('form[action="' . $disableUrl . '"][method="post"]');
        self::assertGreaterThan(
            0,
            $form->count(),
            'Le formulaire de confirmation doit poster vers admin_theme_disable.'
        );

        $submitButton = $form->filter('button[type="submit"]');
        self::assertGreaterThan(0, $submitButton->count());
        self::assertStringContainsString(
            'Confirmer la désactivation',
            trim($submitButton->text())
        );

        $tokenInput = $form->filter('input[name="_token"]');
        self::assertGreaterThan(0, $tokenInput->count(), 'Le formulaire doit contenir un _token.');
        self::assertNotNull($tokenInput->attr('value'));
        self::assertNotSame('', trim((string) $tokenInput->attr('value')), 'Le token CSRF ne doit pas être vide.');
    }

    public function test_cancel_button_links_back_to_index(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', $this->router->generate('admin_theme_delete', [
            'id' => $this->theme->getId(),
        ]));

        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $indexUrl = $this->router->generate('admin_theme_index');

        self::assertGreaterThan(
            0,
            $crawler->filter('a[href="' . $indexUrl . '"]')->count(),
            'Le bouton/lien Annuler doit pointer vers la liste.'
        );

        $cancelLinks = $crawler->filter('a[href="' . $indexUrl . '"]')->reduce(
            static fn (Crawler $node): bool => str_contains($node->text(), 'Annuler')
        );

        self::assertGreaterThan(0, $cancelLinks->count(), 'Un lien "Annuler" vers l’index doit être présent.');
    }

    private function createAdminUser(): User
    {
        $email = 'admin-theme-delete-' . uniqid('', true) . '@example.com';

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