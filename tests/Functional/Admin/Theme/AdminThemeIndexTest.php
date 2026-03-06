<?php

namespace App\Tests\Functional\Admin\Theme;

use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

final class AdminThemeIndexTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private RouterInterface $router;

    private User $admin;

    private Theme $activeTheme;
    private Theme $archivedTheme;
    private Theme $activeThemeWithoutCursus;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->client->followRedirects(true);

        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->router = $container->get(RouterInterface::class);

        $this->admin = $this->createAdminUser();
        $this->activeTheme = $this->createTheme(
            'ZZZ Theme Active Test',
            true,
            new \DateTimeImmutable('2024-01-15 10:00:00')
        );
        $this->archivedTheme = $this->createTheme(
            'ZZZ Theme Archived Test',
            false,
            new \DateTimeImmutable('2023-01-15 10:00:00')
        );
        $this->activeThemeWithoutCursus = $this->createTheme(
            'ZZZ Theme Sans Cursus Actif',
            true,
            new \DateTimeImmutable('2024-06-01 10:00:00')
        );

        $this->em->flush();
    }

    public function test_filters_are_prefilled_and_expected_options_exist(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/themes?q=Theme&status=active&sort=name_desc');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();

        $qInput = $crawler->filter('form.admin-filters input[name="q"]');
        self::assertCount(1, $qInput);
        self::assertSame('Theme', $qInput->attr('value'));

        $statusSelect = $crawler->filter('form.admin-filters select[name="status"]');
        self::assertCount(1, $statusSelect);
        self::assertSelectHasOption($statusSelect, 'all');
        self::assertSelectHasOption($statusSelect, 'active');
        self::assertSelectHasOption($statusSelect, 'archived');
        self::assertOptionSelected($statusSelect, 'active');

        $sortSelect = $crawler->filter('form.admin-filters select[name="sort"]');
        self::assertCount(1, $sortSelect);
        self::assertSelectHasOption($sortSelect, 'created_desc');
        self::assertSelectHasOption($sortSelect, 'created_asc');
        self::assertSelectHasOption($sortSelect, 'name_asc');
        self::assertSelectHasOption($sortSelect, 'name_desc');
        self::assertOptionSelected($sortSelect, 'name_desc');
    }

    public function test_search_and_reset_actions_are_visible(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/themes');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();

        self::assertGreaterThan(
            0,
            $crawler->filter('form.admin-filters button[type="submit"]')->count(),
            'Le bouton de soumission du filtre doit exister.'
        );

        self::assertStringContainsString(
            'Filtrer',
            trim($crawler->filter('form.admin-filters button[type="submit"]')->text()),
            'Le bouton principal devrait être libellé "Filtrer".'
        );

        $resetLink = $crawler->filter('form.admin-filters a[href="' . $this->router->generate('admin_theme_index') . '"]');
        self::assertGreaterThan(0, $resetLink->count(), 'Le lien de reset des filtres doit exister.');
    }

    public function test_active_badge_is_displayed_clearly(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/themes');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();

        $activeCard = $this->findThemeCard($crawler, $this->activeTheme->getName());
        self::assertNotNull($activeCard, 'La carte du thème actif doit être présente.');
        self::assertStringContainsString('Actif', $activeCard->text());

        $archivedCard = $this->findThemeCard($crawler, $this->archivedTheme->getName());
        self::assertNotNull($archivedCard, 'La carte du thème archivé doit être présente.');
        self::assertTrue(
            str_contains($archivedCard->text(), 'Inactif') || str_contains($archivedCard->text(), 'Archivé'),
            'Le statut du thème inactif doit être clairement affiché.'
        );
    }

    public function test_edit_and_delete_confirm_routes_are_present_for_active_theme(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/themes');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $card = $this->findThemeCard($crawler, $this->activeTheme->getName());

        self::assertNotNull($card);

        $editUrl = $this->router->generate('admin_theme_edit', ['id' => $this->activeTheme->getId()]);
        $deleteUrl = $this->router->generate('admin_theme_delete', ['id' => $this->activeTheme->getId()]);

        self::assertGreaterThan(
            0,
            $card->filter('a[href="' . $editUrl . '"]')->count(),
            'Le bouton Modifier doit pointer vers admin_theme_edit.'
        );

        self::assertGreaterThan(
            0,
            $card->filter('a[href="' . $deleteUrl . '"]')->count(),
            'Le bouton Désactiver doit pointer vers admin_theme_delete.'
        );
    }

    public function test_active_theme_has_disable_confirmation_link(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/themes');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $card = $this->findThemeCard($crawler, $this->activeTheme->getName());

        self::assertNotNull($card);

        $deleteUrl = $this->router->generate('admin_theme_delete', ['id' => $this->activeTheme->getId()]);

        self::assertGreaterThan(
            0,
            $card->filter('a[href="' . $deleteUrl . '"]')->count(),
            'Un thème actif doit proposer un lien vers la page de confirmation de désactivation.'
        );

        self::assertStringContainsString('Désactiver', $card->text());
    }

    public function test_archived_theme_has_activate_form_with_csrf_token_present(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/themes');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $card = $this->findThemeCard($crawler, $this->archivedTheme->getName());

        self::assertNotNull($card);

        $activateUrl = $this->router->generate('admin_theme_activate', ['id' => $this->archivedTheme->getId()]);

        $form = $card->filter('form[action="' . $activateUrl . '"][method="post"]');
        self::assertGreaterThan(
            0,
            $form->count(),
            'Un thème inactif doit proposer un formulaire POST vers admin_theme_activate.'
        );

        $tokenInput = $form->filter('input[name="_token"]');
        self::assertGreaterThan(0, $tokenInput->count(), 'Le formulaire activate doit contenir un _token.');
        self::assertNotNull($tokenInput->attr('value'));
        self::assertNotSame('', trim((string) $tokenInput->attr('value')), 'Le token CSRF activate ne doit pas être vide.');
    }

    public function test_active_theme_should_not_show_activate_button(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/themes');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $card = $this->findThemeCard($crawler, $this->activeTheme->getName());

        self::assertNotNull($card);

        self::assertSame(
            0,
            $card->filter('button')->reduce(
                static fn (Crawler $node): bool => str_contains(trim($node->text()), 'Activer')
            )->count(),
            'Un thème actif ne doit pas afficher de bouton Activer.'
        );
    }

    public function test_archived_theme_should_not_show_disable_confirmation_link(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/themes');
        self::assertResponseIsSuccessful();

        $crawler = $this->client->getCrawler();
        $card = $this->findThemeCard($crawler, $this->archivedTheme->getName());

        self::assertNotNull($card);

        $deleteUrl = $this->router->generate('admin_theme_delete', ['id' => $this->archivedTheme->getId()]);

        self::assertSame(
            0,
            $card->filter('a[href="' . $deleteUrl . '"]')->count(),
            'Un thème inactif ne doit pas proposer de lien vers la page de désactivation.'
        );
    }

    public function test_active_filter_should_not_show_active_theme_without_active_cursus(): void
    {
        $this->client->loginUser($this->admin);

        $this->client->request('GET', '/admin/themes?status=active');
        self::assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        self::assertStringNotContainsString(
            $this->activeThemeWithoutCursus->getName(),
            $content,
            'Quand status=active et requireCursus est attendu, un thème actif sans cursus actif ne devrait pas apparaître.'
        );
    }

    private function createAdminUser(): User
    {
        $email = 'admin-theme-index-' . uniqid('', true) . '@example.com';

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

    private function createTheme(string $name, bool $isActive, \DateTimeImmutable $createdAt): Theme
    {
        $theme = (new Theme())
            ->setName($name)
            ->setDescription('Description de test')
            ->setImage('images/test.jpg')
            ->setIsActive($isActive)
            ->setCreatedAt($createdAt);

        $this->em->persist($theme);

        return $theme;
    }

    private function findThemeCard(Crawler $crawler, string $themeName): ?Crawler
    {
        $cards = $crawler->filter('.theme-card')->reduce(
            static fn (Crawler $node): bool => str_contains($node->text(), $themeName)
        );

        return $cards->count() > 0 ? $cards->eq(0) : null;
    }

    private static function assertSelectHasOption(Crawler $select, string $value): void
    {
        self::assertGreaterThan(
            0,
            $select->filter(sprintf('option[value="%s"]', $value))->count(),
            sprintf('L’option "%s" doit être présente.', $value)
        );
    }

    private static function assertOptionSelected(Crawler $select, string $value): void
    {
        self::assertGreaterThan(
            0,
            $select->filter(sprintf('option[value="%s"][selected]', $value))->count(),
            sprintf('L’option "%s" doit être sélectionnée.', $value)
        );
    }
}