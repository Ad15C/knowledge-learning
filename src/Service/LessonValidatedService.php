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

    /**
     * Valide une leçon pour un utilisateur
     */
    public function validateLesson(User $user, Lesson $lesson, ?\App\Entity\PurchaseItem $purchaseItem = null): LessonValidated
    {
        // Vérifier si la leçon est déjà validée
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

        // Créer la validation
        $validation = new LessonValidated();
        $validation->setUser($user)
                   ->setLesson($lesson)
                   ->setPurchaseItem($purchaseItem)
                   ->markCompleted();

        $this->em->persist($validation);
        $this->em->flush();

        // Créer la certification de cette leçon
        $this->createLessonCertification($user, $lesson);

        // Créer la certification du thème si toutes les leçons du thème sont validées
        $cursus = $lesson->getCursus();
        $theme = $cursus?->getTheme();

        if ($theme && $this->lessonValidatedRepo->hasCompletedTheme($user, $theme)) {
            $this->createThemeCertification($user, $theme, $cursus);
        }

        // Créer la certification du cursus si toutes les leçons du cursus sont validées
        if ($cursus && $this->hasCompletedCursus($user, $cursus)) {
            $this->createCursusCertification($user, $cursus);
        }

        return $validation;
    }

    /**
     * Vérifie si toutes les leçons d’un cursus sont validées
     */
    private function hasCompletedCursus(User $user, Cursus $cursus): bool
    {
        $lessons = $cursus->getLessons();
        if ($lessons->isEmpty()) {
            return false;
        }

        $validatedLessons = $this->lessonValidatedRepo->findBy(['user' => $user]);
        $validatedIds = array_map(fn($lv) => $lv->getLesson()->getId(), $validatedLessons);

        foreach ($lessons as $lesson) {
            if (!in_array($lesson->getId(), $validatedIds)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Vérifie si toutes les leçons d’un thème sont validées
     */
    public function isThemeCompleted(User $user, Theme $theme): bool
    {
        return $this->lessonValidatedRepo->hasCompletedTheme($user, $theme);
    }

    /**
     * Crée une certification pour la leçon individuelle
     */
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
             ->setIssuedAt(new \DateTime());

        $this->em->persist($cert);
        $this->em->flush();
    }

    /**
     * Crée une certification pour le cursus
     */
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
             ->setIssuedAt(new \DateTime());

        $this->em->persist($cert);
        $this->em->flush();
    }

    /**
     * Crée une certification pour le thème
     */
    private function createThemeCertification(User $user, Theme $theme, ?Cursus $cursus = null): void
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
             ->setCursus($cursus)
             ->setType('theme')
             ->setCertificateCode(uniqid('KL-'))
             ->setIssuedAt(new \DateTime());

        $this->em->persist($cert);
        $this->em->flush();
    }
}