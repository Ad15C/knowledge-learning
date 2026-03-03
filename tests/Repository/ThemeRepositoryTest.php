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

        $this->em->clear();
    }

    public function testFindThemesWithNoFiltersReturnsOnlyActiveAndSortedByName(): void
    {
        $themes = $this->repo->findThemesWithFilters();

        self::assertNotEmpty($themes);

        // uniquement actifs
        foreach ($themes as $t) {
            self::assertTrue($t->isActive(), 'findThemesWithFilters() doit retourner uniquement des thèmes actifs.');
        }

        // tri ASC sur t.name
        $names = array_map(static fn(Theme $t) => $t->getName(), $themes);
        $sorted = $names;
        sort($sorted, SORT_STRING);
        self::assertSame($sorted, $names, 'Les thèmes doivent être triés par nom ASC.');
    }

    public function testFilterByNameIsCaseInsensitive(): void
    {
        $themes = $this->repo->findThemesWithFilters('muSiQue');

        self::assertCount(1, $themes);
        self::assertSame('Musique', $themes[0]->getName());
        self::assertTrue($themes[0]->isActive());
    }

    public function testFilterByMinPrice(): void
    {
        $themes = $this->repo->findThemesWithFilters(null, 55, null);

        self::assertNotEmpty($themes);

        $names = array_map(static fn(Theme $t) => $t->getName(), $themes);

        // Avec tes fixtures : Informatique a un cursus à 60 => doit passer
        self::assertContains('Informatique', $names);

        // Jardinage (30) ne doit pas passer (sauf si thème sans cursus, mais ici il en a)
        self::assertNotContains('Jardinage', $names);
    }

    public function testFilterByMinAndMaxPrice(): void
    {
        // Entre 45 et 55 => Musique (50) doit passer, Informatique (60) non,
        // Cuisine (44/48) : 48 passe, 44 non => au moins Cuisine peut passer selon query.
        $themes = $this->repo->findThemesWithFilters(null, 45, 55);

        $names = array_map(static fn(Theme $t) => $t->getName(), $themes);

        self::assertContains('Musique', $names);
        self::assertNotContains('Informatique', $names);
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

        // requireCursus => pas de thème sans cursus joint
        foreach ($themes as $theme) {
            self::assertGreaterThan(
                0,
                $theme->getCursus()->count(),
                'requireCursus=true doit exclure les thèmes sans cursus.'
            );
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