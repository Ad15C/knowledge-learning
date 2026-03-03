<?php

namespace App\Tests\Security;

use App\DataFixtures\TestUserFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminAccessTest extends WebTestCase
{
    /**
     * Snapshot attendu (route name, methods, path).
     * Si tu ajoutes/modifies une route /admin, mets à jour ce tableau.
     */
    private const EXPECTED_ADMIN_ROUTES = [
        ['certification_download', 'GET', '/admin/certifications/{id}/download'],
        ['admin_contact_index', 'GET', '/admin/contact/'],
        ['admin_contact_show', 'GET', '/admin/contact/{id}'],
        ['admin_contact_mark_read', 'POST', '/admin/contact/{id}/read'],
        ['admin_contact_mark_unread', 'POST', '/admin/contact/{id}/unread'],
        ['admin_contact_mark_handled', 'POST', '/admin/contact/{id}/handled'],
        ['admin_dashboard', 'ANY', '/admin'],
        ['admin_cursus_index', 'GET', '/admin/cursus'],
        ['admin_cursus_new', 'GET|POST', '/admin/cursus/new'],
        ['admin_cursus_edit', 'GET|POST', '/admin/cursus/{id}/edit'],
        ['admin_cursus_delete', 'GET', '/admin/cursus/{id}/delete'],
        ['admin_cursus_disable', 'POST', '/admin/cursus/{id}/disable'],
        ['admin_cursus_activate', 'POST', '/admin/cursus/{id}/activate'],
        ['admin_lesson_index', 'GET', '/admin/lesson'],
        ['admin_lesson_new', 'GET|POST', '/admin/lesson/new'],
        ['admin_lesson_edit', 'GET|POST', '/admin/lesson/{id}/edit'],
        ['admin_lesson_delete', 'GET', '/admin/lesson/{id}/delete'],
        ['admin_lesson_disable', 'POST', '/admin/lesson/{id}/disable'],
        ['admin_lesson_activate', 'POST', '/admin/lesson/{id}/activate'],
        ['admin_purchase_index', 'GET', '/admin/purchases'],
        ['admin_purchase_show', 'GET', '/admin/purchases/{id}'],
        ['admin_theme_index', 'GET', '/admin/themes'],
        ['admin_theme_new', 'GET|POST', '/admin/themes/new'],
        ['admin_theme_edit', 'GET|POST', '/admin/themes/{id}/edit'],
        ['admin_theme_delete', 'GET', '/admin/themes/{id}/delete'],
        ['admin_theme_disable', 'POST', '/admin/themes/{id}/disable'],
        ['admin_theme_activate', 'POST', '/admin/themes/{id}/activate'],
        ['admin_users_index', 'GET', '/admin/users'],
        ['admin_users_show', 'GET', '/admin/users/{id}'],
        ['admin_users_edit', 'GET|POST', '/admin/users/{id}/edit'],
        ['admin_users_delete', 'POST', '/admin/users/{id}/delete'],
        ['admin_users_restore', 'POST', '/admin/users/{id}/restore'],
    ];

    private function bootAndLoadFixtures(): array
    {
        $client = self::createClient();
        $container = static::getContainer();

        /** @var RouterInterface $router */
        $router = $container->get(RouterInterface::class);

        /** @var DatabaseToolCollection $dbTools */
        $dbTools = $container->get(DatabaseToolCollection::class);
        $executor = $dbTools->get()->loadFixtures([TestUserFixtures::class]);
        $refRepo = $executor->getReferenceRepository();

        /** @var User $user */
        $user = $refRepo->getReference(TestUserFixtures::USER_REF, User::class);

        /** @var User $admin */
        $admin = $refRepo->getReference(TestUserFixtures::ADMIN_REF, User::class);

        return [$client, $container, $router, $user, $admin];
    }

    public function test_admin_routes_security_matrix(): void
    {
        [$client, , $router, $user, $admin] = $this->bootAndLoadFixtures();

        $routes = $this->getAdminRoutes($router);
        $this->assertNotEmpty($routes, 'Aucune route /admin trouvée.');

        // 1) Visiteur => 302 vers /login
        foreach ($routes as [$name, $path, $methods]) {
            $url = $this->fillPlaceholders($path);
            $method = $this->pickMethod($methods);

            $client->request($method, $url);
            $response = $client->getResponse();

            $this->assertTrue(
                $response->isRedirect(),
                sprintf('[VISITEUR] %s %s (%s) devrait rediriger (got %d).', $method, $url, $name, $response->getStatusCode())
            );

            $location = (string) $response->headers->get('Location');
            $this->assertStringContainsString(
                '/login',
                $location,
                sprintf('[VISITEUR] %s %s (%s) devrait rediriger vers /login (Location=%s).', $method, $url, $name, $location)
            );
        }

        // 2) ROLE_USER => 403 partout
        $client->loginUser($user);

        foreach ($routes as [$name, $path, $methods]) {
            $url = $this->fillPlaceholders($path);
            $method = $this->pickMethod($methods);

            $client->request($method, $url);

            $this->assertSame(
                403,
                $client->getResponse()->getStatusCode(),
                sprintf('[USER] %s %s (%s) devrait renvoyer 403 (got %d).', $method, $url, $name, $client->getResponse()->getStatusCode())
            );
        }

        // 3) ROLE_ADMIN => pas 403 ; pas redirect /login ; on accepte 200/302/404
        $client->loginUser($admin);

        foreach ($routes as [$name, $path, $methods]) {
            $url = $this->fillPlaceholders($path);
            $method = $this->pickMethod($methods);

            // POST-only : on ne force pas (CSRF possible) -> on ne valide ici que "pas redirect /login"
            if ($method === 'POST' && !(in_array('GET', $methods, true) || empty($methods))) {
                $client->request('POST', $url);
                $response = $client->getResponse();

                if ($response->isRedirect()) {
                    $location = (string) $response->headers->get('Location');
                    $this->assertStringNotContainsString(
                        '/login',
                        $location,
                        sprintf('[ADMIN][POST] %s (%s) ne doit pas rediriger vers /login (Location=%s).', $url, $name, $location)
                    );
                }
                continue;
            }

            // GET|POST : force GET pour éviter CSRF
            if ($method === 'POST' && in_array('GET', $methods, true)) {
                $method = 'GET';
            }

            $client->request($method, $url);
            $response = $client->getResponse();
            $status = $response->getStatusCode();

            if ($response->isRedirect()) {
                $location = (string) $response->headers->get('Location');
                $this->assertStringNotContainsString(
                    '/login',
                    $location,
                    sprintf('[ADMIN] %s %s (%s) ne doit pas rediriger vers /login (Location=%s).', $method, $url, $name, $location)
                );
            }

            $this->assertNotSame(
                403,
                $status,
                sprintf('[ADMIN] %s %s (%s) ne doit pas renvoyer 403 (got %d).', $method, $url, $name, $status)
            );

            $this->assertContains(
                $status,
                [200, 302, 404],
                sprintf('[ADMIN] %s %s (%s) statut inattendu %d (attendu 200/302/404).', $method, $url, $name, $status)
            );
        }
    }

    public function test_admin_routes_snapshot(): void
    {
        [, , $router] = $this->bootAndLoadFixtures();

        $actual = [];
        foreach ($this->getAdminRoutes($router) as [$name, $path, $methods]) {
            $methodsLabel = empty($methods) ? 'ANY' : implode('|', $methods);
            $actual[] = [$name, $methodsLabel, $path];
        }

        usort($actual, fn($a, $b) => strcmp($a[0], $b[0]));
        $expected = self::EXPECTED_ADMIN_ROUTES;
        usort($expected, fn($a, $b) => strcmp($a[0], $b[0]));

        $this->assertSame(
            $expected,
            $actual,
            "Snapshot des routes /admin différent.\n" .
            "Si tu as ajouté/modifié une route admin, mets à jour EXPECTED_ADMIN_ROUTES."
        );
    }

    public function test_each_admin_route_controller_is_isgranted_admin(): void
    {
        [, , $router] = $this->bootAndLoadFixtures();

        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $path = (string) $route->getPath();
            if (!str_starts_with($path, '/admin')) {
                continue;
            }

            $controller = (string) $route->getDefault('_controller');

            if (!str_contains($controller, '::')) {
                $this->fail(sprintf('Route %s (%s) controller non standard: "%s". Ajoute un contrôle IsGranted explicite.', $name, $path, $controller));
            }

            [$class, $method] = explode('::', $controller, 2);

            if (!class_exists($class)) {
                $this->fail(sprintf('Controller class introuvable pour %s: %s', $name, $class));
            }

            $refClass = new \ReflectionClass($class);

            $hasClassIsGrantedAdmin = $this->hasIsGrantedAdmin($refClass->getAttributes(IsGranted::class));

            $hasMethodIsGrantedAdmin = false;
            if ($refClass->hasMethod($method)) {
                $refMethod = $refClass->getMethod($method);
                $hasMethodIsGrantedAdmin = $this->hasIsGrantedAdmin($refMethod->getAttributes(IsGranted::class));
            }

            $this->assertTrue(
                $hasClassIsGrantedAdmin || $hasMethodIsGrantedAdmin,
                sprintf(
                    'La route %s (%s) pointe vers %s mais aucun #[IsGranted("ROLE_ADMIN")] trouvé sur la classe ou la méthode.',
                    $name,
                    $path,
                    $controller
                )
            );
        }
    }

    public function test_admin_can_archive_and_restore_user_with_valid_csrf(): void
    {
        [$client, $container, , $user, $admin] = $this->bootAndLoadFixtures();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $client->loginUser($admin);

        $targetId = $user->getId();
        $this->assertNotNull($targetId);

        // ===== ARCHIVE : on récupère le token depuis le HTML, puis on submit le vrai formulaire =====
        $crawler = $client->request('GET', '/admin/users?action=delete&status=active');
        $this->assertSame(200, $client->getResponse()->getStatusCode(), 'La page /admin/users doit être accessible en admin.');

        $archiveFormNode = $crawler->filter('form')->reduce(function ($node) use ($targetId) {
            $action = (string) $node->attr('action');
            return str_contains($action, "/admin/users/{$targetId}/delete");
        });

        $this->assertGreaterThan(0, $archiveFormNode->count(), 'Formulaire archive introuvable pour user id=' . $targetId);

        // Soumission du formulaire réel (inclut _token)
        $client->submit($archiveFormNode->form());

        $status = $client->getResponse()->getStatusCode();
        $this->assertNotSame(403, $status, sprintf('Archive: ne devrait pas être 403 (status=%d).', $status));
        $this->assertNotSame(500, $status, sprintf('Archive: ne devrait pas être 500 (status=%d).', $status));

        $em->clear();

        // IMPORTANT : ton Doctrine Filter masque les utilisateurs archivés -> on le désactive pour vérifier l'état
        $filters = $em->getFilters();
        if ($filters->isEnabled('archived_user')) {
            $filters->disable('archived_user');
        }

        $reloaded = $em->getRepository(User::class)->find($targetId);
        $this->assertNotNull($reloaded, 'User introuvable après archive (filter disabled), id=' . $targetId);

        // ===== RESTORE : même logique dans l’onglet archived =====
        $crawlerArchived = $client->request('GET', '/admin/users?action=delete&status=archived');
        $this->assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertGreaterThan(
            0,
            $crawlerArchived->filter(sprintf('form[action*="/admin/users/%d/restore"]', $targetId))->count(),
            'Après archive, le user doit apparaître dans l’onglet archived avec un bouton Restaurer.'
        );

        $restoreFormNode = $crawlerArchived->filter('form')->reduce(function ($node) use ($targetId) {
            $action = (string) $node->attr('action');
            return str_contains($action, "/admin/users/{$targetId}/restore");
        });

        $this->assertGreaterThan(0, $restoreFormNode->count(), 'Formulaire restore introuvable pour user id=' . $targetId);

        $client->submit($restoreFormNode->form());

        $status2 = $client->getResponse()->getStatusCode();
        $this->assertNotSame(403, $status2, sprintf('Restore: ne devrait pas être 403 (status=%d).', $status2));
        $this->assertNotSame(500, $status2, sprintf('Restore: ne devrait pas être 500 (status=%d).', $status2));

        $em->clear();
        $reloaded2 = $em->getRepository(User::class)->find($targetId);
        $this->assertNotNull($reloaded2, 'User introuvable après restore, id=' . $targetId);
        $this->assertFalse($reloaded2->isArchived(), 'Le user devrait être restauré après submit du formulaire /restore.');
    }

    /**
     * @return array<int, array{0:string,1:string,2:array<int,string>}>
     */
    private function getAdminRoutes(RouterInterface $router): array
    {
        $out = [];
        foreach ($router->getRouteCollection()->all() as $name => $route) {
            $path = (string) $route->getPath();
            if (!str_starts_with($path, '/admin')) {
                continue;
            }
            $out[] = [$name, $path, $route->getMethods()];
        }

        usort($out, fn($a, $b) => strcmp($a[1], $b[1]));
        return $out;
    }

    private function pickMethod(array $methods): string
    {
        if (empty($methods)) return 'GET';
        if (in_array('GET', $methods, true)) return 'GET';
        if (in_array('POST', $methods, true)) return 'POST';
        return $methods[0];
    }

    private function fillPlaceholders(string $path): string
    {
        return preg_replace('/\{[^}]+\}/', '1', $path) ?? $path;
    }

    private function hasIsGrantedAdmin(array $attributes): bool
    {
        foreach ($attributes as $attr) {
            /** @var IsGranted $instance */
            $instance = $attr->newInstance();
            if ($instance->attribute === 'ROLE_ADMIN') {
                return true;
            }
        }
        return false;
    }
}