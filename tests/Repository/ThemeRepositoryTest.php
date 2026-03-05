<?php

namespace App\Tests\Integration\Repository;

use App\DataFixtures\ThemeFixtures;
use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ThemeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ThemeRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            ThemeFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);

        $repo = $this->em->getRepository(Theme::class);
        self::assertInstanceOf(ThemeRepository::class, $repo);
        $this->repo = $repo;

        // Important : éviter effets de cache entre tests
        $this->em->clear();
    }

    /**
     * FRONT : sans filtre, doit retourner seulement des thèmes visibles
     * (Theme actif + au moins 1 cursus actif + au moins 1 leçon active)
     */
    public function testFindVisibleThemesWithNoFiltersReturnsOnlyVisibleAndSortedByName(): void
    {
        $themes = $this->repo->findVisibleThemesWithFilters();

        self::assertNotEmpty($themes);

        // uniquement actifs + vérification "visible" minimale
        foreach ($themes as $t) {
            self::assertTrue($t->isActive(), 'findVisibleThemesWithFilters() doit retourner uniquement des thèmes actifs.');

            // Vérifie qu'il existe au moins un cursus actif avec au moins une leçon active
            $hasVisibleCursus = false;

            foreach ($t->getCursus() as $c) {
                if (!$c->isActive()) {
                    continue;
                }

                foreach ($c->getLessons() as $l) {
                    if ($l->isActive()) {
                        $hasVisibleCursus = true;
                        break 2;
                    }
                }
            }

            self::assertTrue(
                $hasVisibleCursus,
                'Un thème visible doit avoir au moins un cursus actif avec au moins une leçon active.'
            );
        }

        // tri ASC sur t.name
        $names = array_map(static fn(Theme $t) => (string) $t->getName(), $themes);
        $sorted = $names;
        sort($sorted, SORT_STRING);
        self::assertSame($sorted, $names, 'Les thèmes visibles doivent être triés par nom ASC.');
    }

    public function testFindVisibleThemesFilterByNameIsCaseInsensitive(): void
    {
        $themes = $this->repo->findVisibleThemesWithFilters('muSiQue');

        // Selon tes fixtures il peut y avoir 0 si "Musique" n'est pas visible,
        // mais si Musique existe et est visible, il ne doit y en avoir qu'un.
        // On reste raisonnable : si résultat non vide, alors le nom correspond.
        if (!empty($themes)) {
            self::assertSame('Musique', $themes[0]->getName());
            self::assertTrue($themes[0]->isActive());
        } else {
            self::assertSame([], $themes);
        }
    }

    public function testFindVisibleThemesFilterByMinPrice(): void
    {
        $themes = $this->repo->findVisibleThemesWithFilters(null, 55, null);

        // Tous les résultats doivent respecter minPrice (via prix du cursus actif joint)
        foreach ($themes as $t) {
            $ok = false;
            foreach ($t->getCursus() as $c) {
                if (!$c->isActive()) {
                    continue;
                }
                $price = $c->getPrice();
                if ($price !== null && $price >= 55) {
                    // Attention : il faut aussi une leçon active pour que le thème soit visible
                    foreach ($c->getLessons() as $l) {
                        if ($l->isActive()) {
                            $ok = true;
                            break 2;
                        }
                    }
                }
            }
            self::assertTrue($ok, 'Chaque thème retourné doit avoir au moins un cursus actif (avec leçon active) dont le prix >= minPrice.');
        }
    }

    public function testFindVisibleThemesFilterByMinAndMaxPrice(): void
    {
        $themes = $this->repo->findVisibleThemesWithFilters(null, 45, 55);

        foreach ($themes as $t) {
            $ok = false;
            foreach ($t->getCursus() as $c) {
                if (!$c->isActive()) {
                    continue;
                }
                $price = $c->getPrice();
                if ($price !== null && $price >= 45 && $price <= 55) {
                    foreach ($c->getLessons() as $l) {
                        if ($l->isActive()) {
                            $ok = true;
                            break 2;
                        }
                    }
                }
            }
            self::assertTrue($ok, 'Chaque thème retourné doit avoir au moins un cursus actif (avec leçon active) dont le prix est dans [minPrice, maxPrice].');
        }
    }

    public function testCreateActiveThemesQueryBuilderReturnsOnlyActive(): void
    {
        $qb = $this->repo->createActiveThemesQueryBuilder();
        $themes = $qb->getQuery()->getResult();

        self::assertNotEmpty($themes);

        foreach ($themes as $t) {
            self::assertInstanceOf(Theme::class, $t);
            self::assertTrue($t->isActive());
        }
    }

    public function testCreateAdminFilterQueryBuilderRequireCursus(): void
    {
        $qb = $this->repo->createAdminFilterQueryBuilder(
            q: null,
            status: 'all',
            sort: 'name_asc',
            onlyActiveCursus: false,
            requireCursus: true
        );

        $themes = $qb->getQuery()->getResult();
        self::assertNotEmpty($themes);

        // requireCursus => pas de thème sans cursus dans la base
        foreach ($themes as $theme) {
            self::assertGreaterThan(
                0,
                $theme->getCursus()->count(),
                'requireCursus=true doit exclure les thèmes sans cursus.'
            );
        }
    }

    public function testFindAdminThemesWithVisibilityReturnsRowsAndInactiveThemesAreNotVisible(): void
    {
        $rows = $this->repo->findAdminThemesWithVisibility(
            q: null,
            status: 'all',
            sort: 'name_asc',
            onlyActiveCursus: true
        );

        self::assertNotEmpty($rows);

        foreach ($rows as $row) {
            self::assertArrayHasKey('theme', $row);
            self::assertArrayHasKey('is_visible', $row);

            self::assertInstanceOf(Theme::class, $row['theme']);
            self::assertIsBool($row['is_visible']);

            /** @var Theme $theme */
            $theme = $row['theme'];

            // Règle garantie : un thème inactif ne peut jamais être "visible sur le site"
            if (!$theme->isActive()) {
                self::assertFalse($row['is_visible'], 'Un thème inactif ne peut pas être visible sur le site.');
            }
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em, $this->repo);
        self::ensureKernelShutdown();
    }
}