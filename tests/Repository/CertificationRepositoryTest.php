<?php

namespace App\Tests\Repository;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\User;
use App\Repository\CertificationRepository;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CertificationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CertificationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->repo = $this->em->getRepository(Certification::class);

        // Recréer le schéma propre (SQLite test)
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        // Charger les fixtures PROPREMENT (ReferenceRepository ok)
        $loader = new Loader();
        $loader->addFixture(self::getContainer()->get(TestUserFixtures::class));
        $loader->addFixture(self::getContainer()->get(ThemeFixtures::class));

        $executor = new ORMExecutor($this->em, new ORMPurger($this->em));
        $executor->execute($loader->getFixtures());

        // On repart avec un EM "clean"
        $this->em->clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em);
    }

    private function getFixtureUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => TestUserFixtures::USER_EMAIL,
        ]);

        $this->assertNotNull($user, 'TestUserFixtures doit créer ' . TestUserFixtures::USER_EMAIL);

        return $user;
    }

    private function getAnyCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy([]);
        $this->assertNotNull($cursus, 'ThemeFixtures doit créer au moins 1 cursus');

        return $cursus;
    }

    private function getTwoCursus(): array
    {
        $all = $this->em->getRepository(Cursus::class)->findBy([], null, 2);
        $this->assertCount(2, $all, 'ThemeFixtures doit créer au moins 2 cursus');

        return $all;
    }

    private function makeCertification(
        User $user,
        ?Cursus $cursus,
        \DateTimeInterface $issuedAt,
        string $code,
        string $type = 'cursus'
    ): Certification {
        $cert = new Certification();
        $cert->setUser($user);
        $cert->setCursus($cursus);
        $cert->setIssuedAt($issuedAt);
        $cert->setCertificateCode($code);
        $cert->setType($type);

        $this->em->persist($cert);

        return $cert;
    }

    public function testCRUDPersistUpdateRemove(): void
    {
        $user = $this->getFixtureUser();
        $cursus = $this->getAnyCursus();

        $cert = $this->makeCertification($user, $cursus, new \DateTime('2026-01-15'), 'CERT-001');
        $this->em->flush();

        $id = $cert->getId();
        $this->assertNotNull($id);

        // READ
        $found = $this->repo->find($id);
        $this->assertInstanceOf(Certification::class, $found);
        $this->assertSame('CERT-001', $found->getCertificateCode());
        $this->assertSame($user->getId(), $found->getUser()->getId());

        // UPDATE
        $found->setCertificateCode('CERT-001-UPDATED');
        $this->em->flush();

        $reloaded = $this->repo->find($id);
        $this->assertSame('CERT-001-UPDATED', $reloaded->getCertificateCode());

        // DELETE
        $this->em->remove($reloaded);
        $this->em->flush();

        $this->assertNull($this->repo->find($id));
    }

    public function testFindByUserOrdersByIssuedAtDesc(): void
    {
        $user = $this->getFixtureUser();
        $cursus = $this->getAnyCursus();

        $this->makeCertification($user, $cursus, new \DateTime('2026-01-01'), 'CERT-OLD');
        $this->makeCertification($user, $cursus, new \DateTime('2026-02-01'), 'CERT-NEW');
        $this->em->flush();

        $results = $this->repo->findByUser($user);

        $this->assertCount(2, $results);
        $this->assertSame('CERT-NEW', $results[0]->getCertificateCode());
        $this->assertSame('CERT-OLD', $results[1]->getCertificateCode());
    }

    public function testFindByUserAndCursus(): void
    {
        $user = $this->getFixtureUser();
        [$cursusA, $cursusB] = $this->getTwoCursus();

        $this->makeCertification($user, $cursusA, new \DateTime('2026-01-10'), 'A-1');
        $this->makeCertification($user, $cursusB, new \DateTime('2026-01-11'), 'B-1');
        $this->em->flush();

        $results = $this->repo->findByUserAndCursus($user, $cursusA);

        $this->assertCount(1, $results);
        $this->assertSame('A-1', $results[0]->getCertificateCode());
    }

    public function testFindByUserAndPeriod(): void
    {
        $user = $this->getFixtureUser();
        $cursus = $this->getAnyCursus();

        $this->makeCertification($user, $cursus, new \DateTime('2026-01-05'), 'IN');
        $this->makeCertification($user, $cursus, new \DateTime('2025-12-20'), 'OUT');
        $this->em->flush();

        $from = new \DateTime('2026-01-01');
        $to   = new \DateTime('2026-01-31');

        $results = $this->repo->findByUserAndPeriod($user, $from, $to);

        $this->assertCount(1, $results);
        $this->assertSame('IN', $results[0]->getCertificateCode());
    }

    public function testCountByUserAndOptionalCursus(): void
    {
        $user = $this->getFixtureUser();
        [$cursusA, $cursusB] = $this->getTwoCursus();

        $this->makeCertification($user, $cursusA, new \DateTime('2026-01-01'), 'A-1');
        $this->makeCertification($user, $cursusA, new \DateTime('2026-01-02'), 'A-2');
        $this->makeCertification($user, $cursusB, new \DateTime('2026-01-03'), 'B-1');
        $this->em->flush();

        $this->assertSame(3, $this->repo->countByUser($user));
        $this->assertSame(2, $this->repo->countByUser($user, $cursusA));
        $this->assertSame(1, $this->repo->countByUser($user, $cursusB));
    }

    public function testCertificationRequiresUserAssociation(): void
    {
        $this->expectException(\Doctrine\DBAL\Exception\NotNullConstraintViolationException::class);

        $cert = new Certification();
        $cert->setCertificateCode('X');
        $cert->setType('cursus');
        $cert->setIssuedAt(new \DateTime('2026-01-01'));

        $this->em->persist($cert);
        $this->em->flush();
    }
}