<?php

namespace Tests\Admin\Certification;

use App\Entity\Certification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

class AdminCertificationDownloadTest extends WebTestCase
{
    public function testDownloadHappyPathReturnsPdfWithProperHeadersFilenameSanitizedAndA4Landscape(): void
    {
        $client = static::createClient();

        $admin = $this->createUser(
            email: 'admin_download_test@example.com',
            roles: ['ROLE_ADMIN']
        );
        $client->loginUser($admin);

        $owner = $this->createUser(
            email: 'cert_owner@example.com',
            roles: []
        );

        $rawCode = 'ABC 12/34:é+%#@!"<>|\\';
        $expectedSanitized = preg_replace('/[^A-Za-z0-9_-]/', '-', $rawCode);

        $cert = $this->createCertification(
            user: $owner,
            certificateCode: $rawCode,
            type: 'lesson'
        );

        $client->request(
            'GET',
            sprintf('/admin/certifications/%d/download', $cert->getId()),
            server: ['HTTPS' => 'on']
        );

        $response = $client->getResponse();
        self::assertResponseIsSuccessful();

        self::assertTrue(
            $response->headers->contains('Content-Type', 'application/pdf'),
            'Content-Type doit être application/pdf'
        );

        $disposition = (string) $response->headers->get('Content-Disposition');
        self::assertStringContainsString('attachment;', $disposition);
        self::assertStringContainsString('filename="certificat-' . $expectedSanitized . '.pdf"', $disposition);

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringStartsWith('%PDF', $content, 'Le contenu doit ressembler à un PDF.');

        $looksLikeA4Landscape =
            (bool) preg_match(
                '/\/MediaBox\s*\[\s*0(?:\.\d+)?\s+0(?:\.\d+)?\s+(84[01-2](?:\.\d+)?)\s+(59[45-6](?:\.\d+)?)\s*\]/',
                $content
            );

        self::assertTrue(
            $looksLikeA4Landscape,
            'Le PDF devrait contenir une MediaBox proche de A4 landscape (≈ 842 x 595).'
        );
    }

    public function testDownloadUnknownIdReturns404WithMessage(): void
    {
        $client = static::createClient();

        $admin = $this->createUser(
            email: 'admin_404_test@example.com',
            roles: ['ROLE_ADMIN']
        );
        $client->loginUser($admin);

        $client->request('GET', '/admin/certifications/99999999/download', server: ['HTTPS' => 'on']);

        self::assertResponseStatusCodeSame(404);

        $content = $client->getResponse()->getContent() ?? '';
        self::assertStringContainsString('Certification introuvable', $content);
    }

    public function testTwigTemplateAutoescapePreventsHtmlInjectionFromCertificateCodeAndType(): void
    {
        static::createClient(); // boot kernel une fois ici

        /** @var Environment $twig */
        $twig = static::getContainer()->get(Environment::class);

        $user = $this->createUser(
            email: 'twig_escape_user@example.com',
            roles: []
        );

        $payload = '<img src="http://example.com/evil.png" onerror="alert(1)"><b>bold</b>';

        $cert = new Certification();
        $cert->setUser($user);
        $cert->setIssuedAt(new \DateTimeImmutable());
        $cert->setType($payload);
        $cert->setCertificateCode($payload);

        $html = $twig->render('user/certification_pdf.html.twig', [
            'cert' => $cert,
        ]);

        self::assertStringContainsString('&lt;img', $html);
        self::assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<b>bold</b>', $html);
    }

    public function testDownloadWhenNotLoggedInRedirectsToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/certifications/1/download', server: ['HTTPS' => 'on']);

        self::assertTrue($client->getResponse()->isRedirection());

        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/login', $location);
    }

    public function testDownloadWhenLoggedInAsUserIsForbidden(): void
    {
        $client = static::createClient();

        $user = $this->createUser(
            email: 'normal_user_download_forbidden@example.com',
            roles: []
        );
        $client->loginUser($user);

        $owner = $this->createUser(email: 'cert_owner_forbidden@example.com', roles: []);
        $cert = $this->createCertification(
            user: $owner,
            certificateCode: 'CODE-OK',
            type: 'lesson'
        );

        $client->request(
            'GET',
            sprintf('/admin/certifications/%d/download', $cert->getId()),
            server: ['HTTPS' => 'on']
        );

        self::assertResponseStatusCodeSame(403);
    }

    // -----------------------
    // Helpers
    // -----------------------

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();
        return $em;
    }

    private function createUser(string $email, array $roles): User
    {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setIsVerified(true);
        $user->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, 'TestPassword123!'));

        $em = $this->em();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createCertification(User $user, string $certificateCode, string $type): Certification
    {
        $cert = new Certification();
        $cert->setUser($user);
        $cert->setIssuedAt(new \DateTimeImmutable());
        $cert->setCertificateCode($certificateCode);
        $cert->setType($type);

        $em = $this->em();
        $em->persist($cert);
        $em->flush();

        return $cert;
    }
}