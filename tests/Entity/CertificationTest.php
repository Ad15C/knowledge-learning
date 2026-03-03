<?php

namespace App\Tests\Entity;

use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\Theme;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CertificationTest extends TestCase
{
    public function testDefaultsOnConstruct(): void
    {
        $cert = new Certification();

        self::assertNull($cert->getId(), 'ID should be null before persistence.');

        self::assertInstanceOf(\DateTimeInterface::class, $cert->getIssuedAt());
        self::assertNull($cert->getUser());
        self::assertNull($cert->getCursus());
        self::assertNull($cert->getTheme());
        self::assertNull($cert->getLesson());
        self::assertNull($cert->getCertificateCode());
        self::assertNull($cert->getType());
    }

    public function testSettersAndGetters(): void
    {
        $cert = new Certification();

        $user = new User();
        $cursus = new Cursus();
        $theme = new Theme();
        $lesson = new Lesson();

        $issuedAt = new \DateTime('2026-02-24 14:00:00');

        $cert->setUser($user)
            ->setCursus($cursus)
            ->setTheme($theme)
            ->setLesson($lesson)
            ->setIssuedAt($issuedAt)
            ->setCertificateCode('CERT-ABC-123')
            ->setType('lesson');

        self::assertSame($user, $cert->getUser());
        self::assertSame($cursus, $cert->getCursus());
        self::assertSame($theme, $cert->getTheme());
        self::assertSame($lesson, $cert->getLesson());

        self::assertSame($issuedAt, $cert->getIssuedAt());
        self::assertSame('CERT-ABC-123', $cert->getCertificateCode());
        self::assertSame('lesson', $cert->getType());
    }

    public function testIssuedAtCanBeOverridden(): void
    {
        $cert = new Certification();

        $issuedAt = new \DateTimeImmutable('2026-02-24 14:00:00');
        $cert->setIssuedAt($issuedAt);

        self::assertSame($issuedAt, $cert->getIssuedAt());
        self::assertInstanceOf(\DateTimeInterface::class, $cert->getIssuedAt());
    }
}