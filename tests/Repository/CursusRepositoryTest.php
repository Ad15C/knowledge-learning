<?php

namespace App\Tests\Repository;

use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Repository\CursusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CursusRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CursusRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = static::getContainer();

        // DB reset + fixtures
        $db = $container->get(DatabaseToolCollection::class)->get();
        $fixtureExecutor = $db->loadFixtures([ThemeFixtures::class]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(CursusRepository::class);

        // Important: on veut un EM clean
        $this->em->clear();

        // Stocke la ref repo dans un static si tu veux, mais ici on refetch via EM ensuite
        $this->refRepo = $fixtureExecutor->getReferenceRepository();
    }

    /** @var \Doctrine\Common\DataFixtures\ReferenceRepository */
    private $refRepo;

    private function getCursusGuitareManaged(): Cursus
    {
        /** @var Cursus $cursusRef */
        $cursusRef = $this->refRepo->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);
        self::assertInstanceOf(Cursus::class, $cursusRef);

        $cursus = $this->em->getRepository(Cursus::class)->find($cursusRef->getId());
        self::assertNotNull($cursus);

        return $cursus;
    }

    public function testFindWithLessons(): void
    {
        $cursus = $this->getCursusGuitareManaged();

        $loaded = $this->repo->findWithLessons($cursus->getId());

        self::assertNotNull($loaded);
        self::assertSame($cursus->getId(), $loaded->getId());

        // ThemeFixtures crée 2 leçons pour le cursus guitare
        self::assertCount(2, $loaded->getLessons());
    }

    public function testFindWithLessonsReturnsNullIfNotFound(): void
    {
        self::assertNull($this->repo->findWithLessons(999999));
    }

    public function testCreateAdminFilterQueryBuilderFiltersByQuery(): void
    {
        // "guitare" doit matcher "Cursus d’initiation à la guitare"
        $qb = $this->repo->createAdminFilterQueryBuilder('guitare', 'all', null, 'name_asc');
        $results = $qb->getQuery()->getResult();

        self::assertNotEmpty($results);

        foreach ($results as $cursus) {
            self::assertInstanceOf(Cursus::class, $cursus);
            self::assertStringContainsStringIgnoringCase('guitare', (string) $cursus->getName());
        }
    }

    public function testCreateAdminFilterQueryBuilderFiltersByStatusActiveAndArchived(): void
    {
        // Actifs
        $active = $this->repo->createAdminFilterQueryBuilder(null, 'active')->getQuery()->getResult();
        self::assertNotEmpty($active);
        foreach ($active as $cursus) {
            self::assertTrue($cursus->isActive());
        }

        // Archived (par défaut fixtures = actifs, donc on en archive un pour tester)
        $one = $this->getCursusGuitareManaged();
        $one->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $archived = $this->repo->createAdminFilterQueryBuilder(null, 'archived')->getQuery()->getResult();
        self::assertNotEmpty($archived);
        foreach ($archived as $cursus) {
            self::assertFalse($cursus->isActive());
        }
    }

    public function testCreateAdminFilterQueryBuilderFiltersByThemeId(): void
    {
        $cursus = $this->getCursusGuitareManaged();
        $themeId = $cursus->getTheme()?->getId();
        self::assertNotNull($themeId);

        $results = $this->repo->createAdminFilterQueryBuilder(null, 'all', $themeId)->getQuery()->getResult();
        self::assertNotEmpty($results);

        foreach ($results as $c) {
            self::assertSame($themeId, $c->getTheme()->getId());
        }
    }

    public function testCreateAdminFilterQueryBuilderSortNameAscAndDesc(): void
    {
        $asc = $this->repo->createAdminFilterQueryBuilder(null, 'all', null, 'name_asc')->getQuery()->getResult();
        self::assertGreaterThanOrEqual(2, count($asc));

        $namesAsc = array_map(fn (Cursus $c) => (string) $c->getName(), $asc);
        $sortedAsc = $namesAsc;
        sort($sortedAsc, SORT_STRING);
        self::assertSame($sortedAsc, $namesAsc);

        $desc = $this->repo->createAdminFilterQueryBuilder(null, 'all', null, 'name_desc')->getQuery()->getResult();
        self::assertGreaterThanOrEqual(2, count($desc));

        $namesDesc = array_map(fn (Cursus $c) => (string) $c->getName(), $desc);
        $sortedDesc = $namesDesc;
        rsort($sortedDesc, SORT_STRING);
        self::assertSame($sortedDesc, $namesDesc);
    }

    public function testCreateAdminFilterQueryBuilderSortPriceAscAndDesc(): void
    {
        // tes fixtures ont des prix non-null => on teste juste l'ordre
        $asc = $this->repo->createAdminFilterQueryBuilder(null, 'all', null, 'price_asc')->getQuery()->getResult();
        self::assertGreaterThanOrEqual(2, count($asc));

        $pricesAsc = array_map(fn (Cursus $c) => $c->getPrice(), $asc);
        $sortedAsc = $pricesAsc;
        sort($sortedAsc);
        self::assertSame($sortedAsc, $pricesAsc);

        $desc = $this->repo->createAdminFilterQueryBuilder(null, 'all', null, 'price_desc')->getQuery()->getResult();
        self::assertGreaterThanOrEqual(2, count($desc));

        $pricesDesc = array_map(fn (Cursus $c) => $c->getPrice(), $desc);
        $sortedDesc = $pricesDesc;
        rsort($sortedDesc);
        self::assertSame($sortedDesc, $pricesDesc);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em, $this->repo, $this->refRepo);
        self::ensureKernelShutdown();
    }
}