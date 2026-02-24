<?php

namespace App\Tests\Controller\User;

use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CertificationsTest extends AbstractUserWebTestCase
{
    private function createCertification(User $user, ?Cursus $cursus, string $code, string $issuedAt = '2026-02-10'): Certification
    {
        $cert = new Certification();
        $cert->setUser($user);
        $cert->setCursus($cursus);
        $cert->setCertificateCode($code);
        $cert->setType('cursus');
        $cert->setIssuedAt(new \DateTime($issuedAt));

        $this->em->persist($cert);
        $this->em->flush();

        return $cert;
    }

    private function getAnyCursus(): Cursus
    {
        $cursus = $this->em->getRepository(Cursus::class)->findOneBy([]);
        self::assertNotNull($cursus, 'ThemeFixtures doit créer au moins 1 cursus');
        return $cursus;
    }

    public function testCertificationsListDisplaysFiltersAndCards(): void
    {
        $client = $this->client;
        $user = $this->getFixtureUser();
        $client->loginUser($user);

        $cursus = $this->getAnyCursus();
        $this->createCertification($user, $cursus, 'CERT-UI-001', '2026-02-01');

        $client->request('GET', '/dashboard/certifications');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('h1', 'Mes Certifications');

        $this->assertSelectorExists('form.dashboard-filters');
        $this->assertSelectorExists('select#cursus');
        $this->assertSelectorExists('input#from');
        $this->assertSelectorExists('input#to');

        $this->assertSelectorTextContains('.dashboard-card', 'CERT-UI-001');
        $this->assertSelectorExists('a.card-link-detail');

        $this->assertSelectorTextContains('a.sidebar-link.active', 'Mes certifications');
        $this->assertSelectorExists('a.btn-back[href="/dashboard"]');
    }

    public function testCertificationsFiltersWork(): void
    {
        $client = $this->client;
        $user = $this->getFixtureUser();
        $client->loginUser($user);

        $all = $this->em->getRepository(Cursus::class)->findAll();
        self::assertGreaterThanOrEqual(2, count($all), 'ThemeFixtures doit créer au moins 2 cursus');

        $cursusA = $all[0];
        $cursusB = $all[1];

        $this->createCertification($user, $cursusA, 'A-IN', '2026-02-05');
        $this->createCertification($user, $cursusA, 'A-OUT', '2026-01-05');
        $this->createCertification($user, $cursusB, 'B-IN', '2026-02-06');

        $client->request('GET', '/dashboard/certifications?cursus='.$cursusA->getId().'&from=2026-02-01&to=2026-02-28');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('.dashboard-content', 'A-IN');
        $this->assertSelectorTextNotContains('.dashboard-content', 'A-OUT');
        $this->assertSelectorTextNotContains('.dashboard-content', 'B-IN');
    }

    public function testShowCertificateDisplaysDataAndButtons(): void
    {
        $client = $this->client;
        $user = $this->getFixtureUser();
        $client->loginUser($user);

        $cursus = $this->getAnyCursus();
        $cert = $this->createCertification($user, $cursus, 'CERT-SHOW-001', '2026-02-10');

        $client->request('GET', '/dashboard/certification/'.$cert->getId());
        $this->assertResponseIsSuccessful();

        $this->assertSelectorTextContains('.certificate-header h1', 'CERTIFICAT OFFICIEL');
        $this->assertSelectorTextContains('.certificate-org', 'Knowledge Learning');
        $this->assertSelectorTextContains('.certificate-code', 'CERT-SHOW-001');

        // Nom user robuste
        $this->assertSelectorTextContains('.certificate-user', $user->getFirstName());
        $this->assertSelectorTextContains('.certificate-user', $user->getLastName());

        // Bouton imprimer : on vérifie qu'il est présent et (si possible) lié à print
        $this->assertSelectorExists('button.btn.btn-primary');
        $this->assertSelectorTextContains('button.btn.btn-primary', 'Imprimer');
        $this->assertSelectorExists('button.btn.btn-primary[onclick*="print"]');

        // lien retour
        $this->assertSelectorExists('a.btn.btn-secondary[href="/dashboard/certifications"]');
    }

    public function testUserCannotViewOtherUsersCertification(): void
    {
        $client = $this->client;

        $owner = $this->getFixtureUser();
        $cursus = $this->getAnyCursus();
        $cert = $this->createCertification($owner, $cursus, 'CERT-SEC-001', '2026-02-10');

        // intrus
        $intruder = new User();
        $intruder->setEmail('intruder@example.com');
        $intruder->setFirstName('In');
        $intruder->setLastName('Truder');
        $intruder->setRoles(['ROLE_USER']);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $intruder->setPassword($hasher->hashPassword($intruder, 'IntruderPass123!'));

        $this->em->persist($intruder);
        $this->em->flush();

        $client->loginUser($intruder);

        $client->request('GET', '/dashboard/certification/'.$cert->getId());

        $this->assertTrue($client->getResponse()->isRedirect('/dashboard/certifications'));
        $client->followRedirect();

        $this->assertSelectorExists('.flash');
        $this->assertSelectorTextContains('.flash', 'Vous n’êtes pas autorisé');
    }

    public function testCertificatePdfIsGenerated(): void
    {
        $client = $this->client;
        $user = $this->getFixtureUser();
        $client->loginUser($user);

        $cursus = $this->getAnyCursus();
        $cert = $this->createCertification($user, $cursus, 'CERT-PDF-001', '2026-02-10');

        $client->request('GET', '/dashboard/certification/'.$cert->getId().'/pdf');

        $this->assertResponseIsSuccessful();

        $contentType = $client->getResponse()->headers->get('content-type');
        $this->assertNotNull($contentType);
        $this->assertStringContainsString('application/pdf', $contentType);

        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    public function testUserCannotDownloadOtherUsersPdf(): void
    {
        $client = $this->client;

        $owner = $this->getFixtureUser();
        $cursus = $this->getAnyCursus();
        $cert = $this->createCertification($owner, $cursus, 'CERT-PDF-SEC', '2026-02-10');

        // intrus
        $intruder = new User();
        $intruder->setEmail('pdf_intruder@example.com');
        $intruder->setFirstName('In');
        $intruder->setLastName('Truder');
        $intruder->setRoles(['ROLE_USER']);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $intruder->setPassword($hasher->hashPassword($intruder, 'IntruderPass123!'));

        $this->em->persist($intruder);
        $this->em->flush();

        $client->loginUser($intruder);

        $client->request('GET', '/dashboard/certification/'.$cert->getId().'/pdf');
        $this->assertTrue($client->getResponse()->isRedirect('/dashboard/certifications'));
    }
}