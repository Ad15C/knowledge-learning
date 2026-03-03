<?php

namespace App\Tests\Controller;

use App\Entity\Theme;
use App\Entity\User;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        // Nettoyage base avant chaque test
        // Attention à l'ordre (items avant purchase)
        $this->em->createQuery('DELETE FROM App\Entity\PurchaseItem')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Purchase')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Theme')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    private function forceOrderNumber(Purchase $purchase): void
    {
        $ref = new \ReflectionClass(Purchase::class);
        $propOrderNumber = $ref->getProperty('orderNumber');
        $propOrderNumber->setAccessible(true);
        $propOrderNumber->setValue(
            $purchase,
            'ORD-TEST-' . date('YmdHis') . '-' . bin2hex(random_bytes(4))
        );
    }

    private function createThemes(): void
    {
        $t1 = (new Theme())
            ->setName('Symfony')
            ->setDescription('Framework PHP')
            ->setImage('images/symfony.png');

        $t2 = (new Theme())
            ->setName('Docker')
            ->setDescription('Container')
            ->setImage('images/docker.png');

        $this->em->persist($t1);
        $this->em->persist($t2);
        $this->em->flush();
    }

    private function createUser(string $role = 'ROLE_USER'): User
    {
        $user = (new User())
            ->setEmail('user@test.com')
            ->setPassword('password')
            ->setFirstname('John')
            ->setLastname('Doe')
            ->setRoles([$role])
            ->setIsVerified(true);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Crée un panier en base : Purchase(status=cart) + N PurchaseItem.
     * Le CartService renvoie count(items), donc N => badge N.
     */
    private function createCartInDb(User $user, int $itemsCount = 1): void
    {
        $purchase = (new Purchase())
            ->setUser($user)
            ->setStatus(Purchase::STATUS_CART);

        // orderNumber obligatoire (NOT NULL + unique)
        $this->forceOrderNumber($purchase);

        $this->em->persist($purchase);

        for ($i = 0; $i < $itemsCount; $i++) {
            $item = (new PurchaseItem())
                ->setPurchase($purchase)
                ->setQuantity(1)
                ->setUnitPrice(10.00); // obligatoire (non nullable)

            // IMPORTANT : ton PurchaseItem peut avoir lesson/cursus null => OK chez toi
            $this->em->persist($item);

            // relation bidirectionnelle
            $purchase->addItem($item);
        }

        // optionnel : recalcul total si tu l'utilises ailleurs
        $purchase->calculateTotal();

        $this->em->flush();
    }

    /** PAGE ACCESSIBLE */
    public function testHomepageLoads(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Bienvenue sur Knowledge Learning');
    }

    /** THEMES AFFICHÉS */
    public function testThemesDisplayed(): void
    {
        $this->createThemes();
        $crawler = $this->client->request('GET', '/');
        $this->assertCount(2, $crawler->filter('.theme-card'));
    }

    /** AUCUN THEME */
    public function testNoThemesMessage(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertSelectorTextContains('body', 'Aucun thème disponible');
    }

    /** LIENS THEMES */
    public function testThemeLinks(): void
    {
        $this->createThemes();
        $crawler = $this->client->request('GET', '/');

        $links = $crawler->filter('.theme-card a.btn');
        $this->assertGreaterThan(0, $links->count());

        foreach ($links as $link) {
            $this->assertStringContainsString('/themes/', $link->getAttribute('href'));
        }
    }

    /** MENU VISITEUR */
    public function testVisitorMenu(): void
    {
        $this->client->request('GET', '/');
        $this->assertSelectorExists('a[href="/login"]');
        $this->assertSelectorExists('a[href="/register"]');

        // Avec ton base.html.twig actuel, le visiteur n'a PAS "Panier"
        $this->assertSelectorNotExists('.menu-badge');
    }

    /** MENU USER CONNECTÉ */
    public function testUserMenu(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/');

        $urlDashboard = self::getContainer()->get('router')->generate('user_dashboard');
        $this->assertSelectorExists('a[href="' . $urlDashboard . '"]');

        $urlLogout = self::getContainer()->get('router')->generate('app_logout');
        $this->assertSelectorExists('a[href="' . $urlLogout . '"]');

        $urlCart = self::getContainer()->get('router')->generate('cart_show');
        $this->assertSelectorExists('a[href="' . $urlCart . '"]');
    }

    /**
     * User connecté + pas de Purchase(status=cart) => badge absent
     */
    public function testCartBadgeHiddenWhenEmpty(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/');

        $this->assertSelectorNotExists('.menu-badge');
    }

    /**
     * User connecté + Purchase(status=cart) avec 2 items => badge "2"
     */
    public function testCartBadgeVisibleWhenCartHasItems(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        // Crée un panier en DB avec 2 items
        $this->createCartInDb($user, 2);

        $this->client->request('GET', '/');

        $this->assertSelectorExists('.menu-badge');
        $this->assertSelectorTextContains('.menu-badge', '2');
    }

    /** PERFORMANCE */
    public function testHomepagePerformance(): void
    {
        $start = microtime(true);
        $this->client->request('GET', '/');
        $time = microtime(true) - $start;
        $this->assertLessThan(1, $time, 'Homepage too slow');
    }
}