<?php

namespace App\Service;

use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\User;
use App\Entity\Certification;
use App\Entity\Theme;
use App\Entity\Cursus;
use App\Repository\LessonValidatedRepository;
use Doctrine\ORM\EntityManagerInterface;

class LessonValidatedService
{
    private EntityManagerInterface $em;
    private LessonValidatedRepository $lessonValidatedRepo;

    public function __construct(EntityManagerInterface $em, LessonValidatedRepository $lessonValidatedRepo)
    {
        $this->em = $em;
        $this->lessonValidatedRepo = $lessonValidatedRepo;
    }

    public function validateLesson(User $user, Lesson $lesson, ?\App\Entity\PurchaseItem $purchaseItem = null): LessonValidated
    {
        $existing = $this->lessonValidatedRepo->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
        ]);

        if ($existing) {
            if (!$existing->isCompleted()) {
                $existing->markCompleted();
                $this->em->flush();
            }
            return $existing;
        }

        $validation = new LessonValidated();
        $validation->setUser($user)
            ->setLesson($lesson)
            ->setPurchaseItem($purchaseItem)
            ->markCompleted();

        $this->em->persist($validation);
        $this->em->flush();

        $cursus = $lesson->getCursus();
        $theme = $cursus->getTheme();

        if ($this->lessonValidatedRepo->hasCompletedTheme($user, $theme)) {
            $this->createThemeCertification($user, $theme, $cursus);
        }

        if ($this->hasCompletedCursus($user, $cursus)) {
            $this->createCursusCertification($user, $cursus);
        }

        return $validation;
    }

    public function isThemeCompleted(User $user, Theme $theme): bool
    {
        return $this->lessonValidatedRepo->hasCompletedTheme($user, $theme);
    }

    private function hasCompletedCursus(User $user, Cursus $cursus): bool
    {
        $theme = $cursus->getTheme();
        return $this->lessonValidatedRepo->hasCompletedTheme($user, $theme);
    }

    private function createThemeCertification(User $user, Theme $theme, Cursus $cursus): void
    {
        $existingCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'theme' => $theme,
            'type' => 'theme',
        ]);

        if ($existingCert) return;

        $cert = new Certification();
        $cert->setUser($user)
             ->setCursus($cursus)
             ->setTheme($theme)
             ->setType('theme')
             ->setCertificateCode(uniqid('KL-'))
             ->setIssuedAt(new \DateTime());

        $this->em->persist($cert);
        $this->em->flush();
    }

    private function createCursusCertification(User $user, Cursus $cursus): void
    {
        $existingCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);

        if ($existingCert) return;

        $cert = new Certification();
        $cert->setUser($user)
             ->setCursus($cursus)
             ->setType('cursus')
             ->setCertificateCode(uniqid('KL-'))
             ->setIssuedAt(new \DateTime());

        $this->em->persist($cert);
        $this->em->flush();
    }
}
