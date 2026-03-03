<?php

namespace App\Tests\Repository;

use App\DataFixtures\TestUserFixtures;
use App\DataFixtures\ThemeFixtures;
use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\User;
use App\Repository\CertificationRepository;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CertificationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CertificationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();

        // ✅ reset DB + load fixtures (simple, fiable)
        $container->get(DatabaseToolCollection::class)->get()->loadFixtures([
            ThemeFixtures::class,
            TestUserFixtures::class,
        ]);

        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $this->em->getRepository(Certification::class);

        // EM clean
        $this->em->clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (isset($this->em)) {
            $this->em->close();
        }

        unset($this->em, $this->repo);
    }

    private function getFixtureUser(): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => TestUserFixtures::USER_EMAIL,
        ]);

        self::assertNotNull($user, 'TestUserFixtures doit créer ' . TestUserFixtures::USER_EMAIL);

        return $user;
    }

    private function getAnyCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy([]);
        self::assertNotNull($cursus, 'ThemeFixtures doit créer au moins 1 cursus');

        return $cursus;
    }

    /**
     * @return array{0:Cursus,1:Cursus}
     */
    private function getTwoCursus(): array
    {
        $all = $this->em->getRepository(Cursus::class)->findBy([], ['id' => 'ASC'], 2);
        self::assertCount(2, $all, 'ThemeFixtures doit créer au moins 2 cursus');

        return [$all[0], $all[1]];
    }

    private function makeCertification(
        User $user,
        ?Cursus $cursus,
        \DateTimeInterface $issuedAt,
        string $code,
        string $type = 'cursus'
    ): Certification {
        $cert = new Certification();
        $cert->setUser($user)
            ->setCursus($cursus)
            ->setIssuedAt($issuedAt)
            ->setCertificateCode($code)
            ->setType($type);

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
        self::assertNotNull($id);

        // READ
        $found = $this->repo->find($id);
        self::assertInstanceOf(Certification::class, $found);
        self::assertSame('CERT-001', $found->getCertificateCode());
        self::assertSame($user->getId(), $found->getUser()->getId());

        // UPDATE
        $found->setCertificateCode('CERT-001-UPDATED');
        $this->em->flush();

        $reloaded = $this->repo->find($id);
        self::assertSame('CERT-001-UPDATED', $reloaded->getCertificateCode());

        // DELETE
        $this->em->remove($reloaded);
        $this->em->flush();

        self::assertNull($this->repo->find($id));
    }

    public function testFindByUserOrdersByIssuedAtDesc(): void
    {
        $user = $this->getFixtureUser();
        $cursus = $this->getAnyCursus();

        $this->makeCertification($user, $cursus, new \DateTime('2026-01-01'), 'CERT-OLD');
        $this->makeCertification($user, $cursus, new \DateTime('2026-02-01'), 'CERT-NEW');
        $this->em->flush();

        $results = $this->repo->findByUser($user);

        self::assertCount(2, $results);
        self::assertSame('CERT-NEW', $results[0]->getCertificateCode());
        self::assertSame('CERT-OLD', $results[1]->getCertificateCode());
    }

    public function testFindByUserAndCursus(): void
    {
        $user = $this->getFixtureUser();
        [$cursusA, $cursusB] = $this->getTwoCursus();

        $this->makeCertification($user, $cursusA, new \DateTime('2026-01-10'), 'A-1');
        $this->makeCertification($user, $cursusB, new \DateTime('2026-01-11'), 'B-1');
        $this->em->flush();

        $results = $this->repo->findByUserAndCursus($user, $cursusA);

        self::assertCount(1, $results);
        self::assertSame('A-1', $results[0]->getCertificateCode());
    }

    public function testFindByUserAndPeriod(): void
    {
        $user = $this->getFixtureUser();
        $cursus = $this->getAnyCursus();

        $this->makeCertification($user, $cursus, new \DateTime('2026-01-05'), 'IN');
        $this->makeCertification($user, $cursus, new \DateTime('2025-12-20'), 'OUT');
        $this->em->flush();

        $from = new \DateTime('2026-01-01 00:00:00');
        $to   = new \DateTime('2026-01-31 23:59:59');

        $results = $this->repo->findByUserAndPeriod($user, $from, $to);

        self::assertCount(1, $results);
        self::assertSame('IN', $results[0]->getCertificateCode());
    }

    public function testCountByUserAndOptionalCursus(): void
    {
        $user = $this->getFixtureUser();
        [$cursusA, $cursusB] = $this->getTwoCursus();

        $this->makeCertification($user, $cursusA, new \DateTime('2026-01-01'), 'A-1');
        $this->makeCertification($user, $cursusA, new \DateTime('2026-01-02'), 'A-2');
        $this->makeCertification($user, $cursusB, new \DateTime('2026-01-03'), 'B-1');
        $this->em->flush();

        self::assertSame(3, $this->repo->countByUser($user));
        self::assertSame(2, $this->repo->countByUser($user, $cursusA));
        self::assertSame(1, $this->repo->countByUser($user, $cursusB));
    }

    public function testFindByUserWithTargetsLoadsRelations(): void
    {
        $user = $this->getFixtureUser();
        $cursus = $this->getAnyCursus();

        $this->makeCertification($user, $cursus, new \DateTime('2026-02-01'), 'CERT-REL', 'cursus');
        $this->em->flush();

        $results = $this->repo->findByUserWithTargets($user);

        self::assertNotEmpty($results);
        self::assertSame('CERT-REL', $results[0]->getCertificateCode());

        // la méthode fait des leftJoin + addSelect: les relations doivent être disponibles
        self::assertSame($user->getId(), $results[0]->getUser()->getId());
        // cursus est nullable, mais ici on l’a mis
        self::assertNotNull($results[0]->getCursus());
    }

    public function testCertificationRequiresUserAssociation(): void
    {
        $this->expectException(NotNullConstraintViolationException::class);

        $cert = new Certification();
        $cert->setCertificateCode('X')
            ->setType('cursus')
            ->setIssuedAt(new \DateTime('2026-01-01'));

        $this->em->persist($cert);
        $this->em->flush();
    }
}