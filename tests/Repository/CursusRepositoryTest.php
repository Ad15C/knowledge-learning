<?php

namespace App\Tests\Repository;

use App\DataFixtures\ThemeFixtures;
use App\Entity\Cursus;
use App\Repository\CursusRepository;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CursusRepositoryTest extends KernelTestCase
{
    public function testFindWithLessons(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $db = $container->get(DatabaseToolCollection::class)->get();
        $fixtures = $db->loadFixtures([ThemeFixtures::class]);

        $cursus = $fixtures->getReferenceRepository()
            ->getReference(ThemeFixtures::CURSUS_GUITARE_REF, Cursus::class);

        $repo = $container->get(CursusRepository::class);

        $loaded = $repo->findWithLessons($cursus->getId());

        self::assertNotNull($loaded);
        self::assertSame($cursus->getId(), $loaded->getId());
        self::assertCount(2, $loaded->getLessons());
    }

    public function testFindWithLessonsReturnsNullIfNotFound(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $db = $container->get(DatabaseToolCollection::class)->get();
        $db->loadFixtures([ThemeFixtures::class]);

        $repo = $container->get(CursusRepository::class);

        self::assertNull($repo->findWithLessons(999999));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::ensureKernelShutdown();
    }
}