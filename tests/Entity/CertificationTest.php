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

        $this->assertInstanceOf(\DateTimeInterface::class, $cert->getIssuedAt());
        $this->assertNull($cert->getUser());
        $this->assertNull($cert->getCursus());
        $this->assertNull($cert->getTheme());
        $this->assertNull($cert->getLesson());
        $this->assertNull($cert->getCertificateCode());
        $this->assertNull($cert->getType());
    }

    public function testSettersAndGetters(): void
    {
        $cert = new Certification();

        $user = $this->createMock(User::class);
        $cursus = $this->createMock(Cursus::class);
        $theme = $this->createMock(Theme::class);
        $lesson = $this->createMock(Lesson::class);
        $issuedAt = new \DateTime('2026-02-24 14:00:00');

        $cert->setUser($user)
            ->setCursus($cursus)
            ->setTheme($theme)
            ->setLesson($lesson)
            ->setIssuedAt($issuedAt)
            ->setCertificateCode('CERT-ABC-123')
            ->setType('lesson');

        $this->assertSame($user, $cert->getUser());
        $this->assertSame($cursus, $cert->getCursus());
        $this->assertSame($theme, $cert->getTheme());
        $this->assertSame($lesson, $cert->getLesson());
        $this->assertSame($issuedAt, $cert->getIssuedAt());
        $this->assertSame('CERT-ABC-123', $cert->getCertificateCode());
        $this->assertSame('lesson', $cert->getType());
    }
}