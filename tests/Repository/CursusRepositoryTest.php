<?php

namespace App\Tests\Repository;

use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use App\Repository\CursusRepository;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CursusRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CursusRepository $repo;

    /** @var ReferenceRepository */
    private $refRepo;

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

        $this->refRepo = $fixtureExecutor->getReferenceRepository();
    }

    private function getCursusGuitareManaged(): Cursus
    {
        /** @var Cursus $cursusRef */
        $cursusRef = $this->refRepo->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);
        self::assertInstanceOf(Cursus::class, $cursusRef);

        /** @var Cursus|null $cursus */
        $cursus = $this->em->getRepository(Cursus::class)->find($cursusRef->getId());
        self::assertNotNull($cursus);

        return $cursus;
    }

    private function getThemeOfCursusManaged(Cursus $cursus): Theme
    {
        $themeId = $cursus->getTheme()?->getId();
        self::assertNotNull($themeId);

        /** @var Theme|null $theme */
        $theme = $this->em->getRepository(Theme::class)->find($themeId);
        self::assertNotNull($theme);

        return $theme;
    }

    // -------------------- ADMIN --------------------

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
        // Tes fixtures ont des prix non-null => on teste juste l'ordre
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

    // -------------------- FRONT (nouveaux tests) --------------------

    public function testFindVisibleByThemeReturnsOnlyVisibleCursus(): void
    {
        $cursus = $this->getCursusGuitareManaged();
        $theme = $this->getThemeOfCursusManaged($cursus);

        // Cas "OK" : thème actif, cursus actif, au moins 1 leçon active
        $results = $this->repo->findVisibleByTheme($theme);

        self::assertNotEmpty($results);

        foreach ($results as $c) {
            self::assertTrue($c->isActive(), 'Le cursus doit être actif');
            self::assertNotNull($c->getTheme());
            self::assertTrue($c->getTheme()->isActive(), 'Le thème doit être actif');
            self::assertSame($theme->getId(), $c->getTheme()->getId(), 'Doit appartenir au thème demandé');

            // grâce au innerJoin sur lessons actives => au moins 1 leçon active
            $hasAtLeastOneActiveLesson = false;
            foreach ($c->getLessons() as $lesson) {
                if ($lesson instanceof Lesson && $lesson->isActive()) {
                    $hasAtLeastOneActiveLesson = true;
                    break;
                }
            }
            self::assertTrue($hasAtLeastOneActiveLesson, 'Doit avoir au moins une leçon active');
        }
    }

    public function testFindVisibleByThemeExcludesCursusIfThemeIsArchived(): void
    {
        $cursus = $this->getCursusGuitareManaged();
        $theme = $this->getThemeOfCursusManaged($cursus);

        // Archive le thème
        $theme->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        /** @var Theme $themeReload */
        $themeReload = $this->em->getRepository(Theme::class)->find($theme->getId());
        self::assertNotNull($themeReload);

        $results = $this->repo->findVisibleByTheme($themeReload);

        // Doit être vide car t.isActive = true est requis
        self::assertSame([], $results);
    }

    public function testFindVisibleWithVisibleLessonsReturnsCursusAndOnlyActiveLessons(): void
    {
        $cursus = $this->getCursusGuitareManaged();

        // Met 1 leçon inactive (sur les 2)
        $lessons = $cursus->getLessons();
        self::assertGreaterThanOrEqual(2, $lessons->count());

        /** @var Lesson $firstLesson */
        $firstLesson = $lessons->first();
        self::assertInstanceOf(Lesson::class, $firstLesson);

        $firstLesson->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->repo->findVisibleWithVisibleLessons($cursus->getId());

        self::assertNotNull($loaded);
        self::assertSame($cursus->getId(), $loaded->getId());

        // La requête INNER JOIN sur l.isActive=true ne doit ramener que les leçons actives
        foreach ($loaded->getLessons() as $lesson) {
            self::assertInstanceOf(Lesson::class, $lesson);
            self::assertTrue($lesson->isActive(), 'Seules les leçons actives doivent être hydratées');
        }

        // On avait 2 leçons et on en a désactivé 1 => il ne doit en rester qu'1 visible ici
        self::assertCount(1, $loaded->getLessons());
    }

    public function testFindVisibleWithVisibleLessonsReturnsNullIfCursusArchived(): void
    {
        $cursus = $this->getCursusGuitareManaged();

        $cursus->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->repo->findVisibleWithVisibleLessons($cursus->getId()));
    }

    public function testFindVisibleWithVisibleLessonsReturnsNullIfThemeArchived(): void
    {
        $cursus = $this->getCursusGuitareManaged();
        $theme = $this->getThemeOfCursusManaged($cursus);

        $theme->setIsActive(false);
        $this->em->flush();
        $this->em->clear();

        self::assertNull($this->repo->findVisibleWithVisibleLessons($cursus->getId()));
    }

    public function testFindVisibleWithVisibleLessonsReturnsNullIfNoActiveLessons(): void
    {
        $cursus = $this->getCursusGuitareManaged();

        foreach ($cursus->getLessons() as $lesson) {
            if ($lesson instanceof Lesson) {
                $lesson->setIsActive(false);
            }
        }

        $this->em->flush();
        $this->em->clear();

        // INNER JOIN l.isActive=true => aucun match => pas de cursus retourné
        self::assertNull($this->repo->findVisibleWithVisibleLessons($cursus->getId()));
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