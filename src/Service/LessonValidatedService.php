<?php

namespace App\Service;

use App\Entity\Certification;
use App\Entity\Cursus;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\Theme;
use App\Entity\User;
use App\Repository\LessonValidatedRepository;
use Doctrine\ORM\EntityManagerInterface;

class LessonValidatedService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LessonValidatedRepository $lessonValidatedRepo
    ) {
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

                $this->createLessonCertification($user, $lesson);
                $this->em->flush();

                $cursus = $lesson->getCursus();
                $theme = $cursus?->getTheme();

                if ($cursus && $this->hasCompletedCursus($user, $cursus)) {
                    $this->createCursusCertification($user, $cursus);
                }

                if ($theme && $this->lessonValidatedRepo->hasCompletedTheme($user, $theme)) {
                    $this->createThemeCertification($user, $theme);
                }

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

        $this->createLessonCertification($user, $lesson);

        // Important : on flush d'abord la validation courante
        // pour qu'elle soit visible dans les vérifications suivantes.
        $this->em->flush();

        $cursus = $lesson->getCursus();
        $theme = $cursus?->getTheme();

        if ($cursus && $this->hasCompletedCursus($user, $cursus)) {
            $this->createCursusCertification($user, $cursus);
        }

        if ($theme && $this->lessonValidatedRepo->hasCompletedTheme($user, $theme)) {
            $this->createThemeCertification($user, $theme);
        }

        $this->em->flush();

        return $validation;
    }

    private function hasCompletedCursus(User $user, Cursus $cursus): bool
    {
        $lessons = $cursus->getLessons();

        if ($lessons->isEmpty()) {
            return false;
        }

        $validatedLessons = $this->lessonValidatedRepo->findBy([
            'user' => $user,
            'completed' => true,
        ]);

        $validatedIds = array_filter(array_map(
            static fn(LessonValidated $lv) => $lv->getLesson()?->getId(),
            $validatedLessons
        ));

        foreach ($lessons as $lesson) {
            if (!in_array($lesson->getId(), $validatedIds, true)) {
                return false;
            }
        }

        return true;
    }

    public function isThemeCompleted(User $user, Theme $theme): bool
    {
        return $this->lessonValidatedRepo->hasCompletedTheme($user, $theme);
    }

    private function createLessonCertification(User $user, Lesson $lesson): void
    {
        $existingCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'lesson' => $lesson,
            'type' => 'lesson',
        ]);

        if ($existingCert) {
            return;
        }

        $cert = new Certification();
        $cert->setUser($user)
            ->setLesson($lesson)
            ->setType('lesson')
            ->setCertificateCode(uniqid('KL-'))
            ->setIssuedAt(new \DateTimeImmutable());

        $this->em->persist($cert);
    }

    private function createCursusCertification(User $user, Cursus $cursus): void
    {
        $existingCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'cursus' => $cursus,
            'type' => 'cursus',
        ]);

        if ($existingCert) {
            return;
        }

        $cert = new Certification();
        $cert->setUser($user)
            ->setCursus($cursus)
            ->setType('cursus')
            ->setCertificateCode(uniqid('KL-'))
            ->setIssuedAt(new \DateTimeImmutable());

        $this->em->persist($cert);
    }

    private function createThemeCertification(User $user, Theme $theme): void
    {
        $existingCert = $this->em->getRepository(Certification::class)->findOneBy([
            'user' => $user,
            'theme' => $theme,
            'type' => 'theme',
        ]);

        if ($existingCert) {
            return;
        }

        $cert = new Certification();
        $cert->setUser($user)
            ->setTheme($theme)
            ->setType('theme')
            ->setCertificateCode(uniqid('KL-'))
            ->setIssuedAt(new \DateTimeImmutable());

        $this->em->persist($cert);
    }
}