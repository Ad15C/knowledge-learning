<?php

namespace App\Controller;

use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\PurchaseItem;
use App\Entity\Certification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class LessonController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * Récupère les leçons accessibles et les leçons déjà complétées pour l'utilisateur courant
     *
     * @return array [userHasAccess, userHasCompleted]
     */
    private function getUserAccessAndCompleted(): array
    {
        $user = $this->getUser();

        $userHasAccess = [];
        $userHasCompleted = [];

        if (!$user) {
            return [$userHasAccess, $userHasCompleted];
        }

        // Récupérer tous les PurchaseItems payés de l'utilisateur
        $paidItems = $this->em->getRepository(PurchaseItem::class)
            ->createQueryBuilder('pi')
            ->join('pi.purchase', 'p')
            ->andWhere('p.user = :user')
            ->andWhere('p.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getResult();

        foreach ($paidItems as $item) {
            // Leçon individuelle
            if ($item->getLesson()) {
                $userHasAccess[$item->getLesson()->getId()] = true;
            }

            // Cursus complet
            if ($item->getCursus()) {
                foreach ($item->getCursus()->getLessons() as $lesson) {
                    $userHasAccess[$lesson->getId()] = true;
                }
            }
        }

        // Récupérer les leçons déjà validées
        $validatedLessons = $this->em
            ->getRepository(LessonValidated::class)
            ->findBy(['user' => $user]);

        foreach ($validatedLessons as $validation) {
            $userHasCompleted[$validation->getLesson()->getId()] = true;
        }

        return [$userHasAccess, $userHasCompleted];
    }

    #[Route('/lesson/{id}', name: 'lesson_show')]
    public function show(Lesson $lesson): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        [$userHasAccess, $userHasCompleted] = $this->getUserAccessAndCompleted();

        return $this->render('lesson/show.html.twig', [
            'lesson' => $lesson,
            'userHasAccess' => $userHasAccess,
            'userHasCompleted' => $userHasCompleted,
        ]);
    }

    #[Route('/lesson/{id}/complete', name: 'lesson_complete', methods: ['POST'])]
    public function complete(Lesson $lesson, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Marquer la leçon comme complétée
        if (!$user->getCompletedLessons()->contains($lesson)) {
            $user->addCompletedLesson($lesson);
            $this->em->flush();
        }

        $this->addFlash('success', 'Leçon marquée comme complétée');

        return $this->redirectToRoute('lesson_show', ['id' => $lesson->getId()]);
    }

    #[Route('/lesson/validate/{id}', name: 'lesson_validate')]
    public function validate(Lesson $lesson): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        [$userHasAccess, $userHasCompleted] = $this->getUserAccessAndCompleted();

        // Sécurité : vérifier que l'utilisateur a accès à la leçon
        if (!isset($userHasAccess[$lesson->getId()])) {
            $this->addFlash('error', 'Vous ne pouvez pas valider cette leçon.');
            return $this->redirectToRoute('cart_show');
        }

        // Éviter la double validation
        if (!isset($userHasCompleted[$lesson->getId()])) {
            $validation = new LessonValidated();
            $validation->setUser($user);
            $validation->setLesson($lesson);

            $this->em->persist($validation);
            $this->em->flush();
        }

        // Vérifier si l'utilisateur peut obtenir la certification du cursus
        $cursus = $lesson->getCursus();
        if ($cursus) {
            $allLessons = $cursus->getLessons();

            $validatedLessons = $this->em
                ->getRepository(LessonValidated::class)
                ->createQueryBuilder('lv')
                ->andWhere('lv.user = :user')
                ->andWhere('lv.lesson IN (:lessons)')
                ->setParameter('user', $user)
                ->setParameter('lessons', $allLessons)
                ->getQuery()
                ->getResult();

            if (count($validatedLessons) === count($allLessons)) {
                $existingCert = $this->em
                    ->getRepository(Certification::class)
                    ->findOneBy(['user' => $user, 'cursus' => $cursus]);

                if (!$existingCert) {
                    $cert = new Certification();
                    $cert->setUser($user);
                    $cert->setCursus($cursus);
                    $cert->setIssuedAt(new \DateTimeImmutable());
                    $cert->setCertificateCode('CERT-' . strtoupper(bin2hex(random_bytes(5))));
                    $cert->setType('cursus');

                    $this->em->persist($cert);
                    $this->em->flush();

                    $this->addFlash('success', 'Certification obtenue !');
                }
            }
        }

        $this->addFlash('success', 'Leçon complétée.');

        return $this->redirectToRoute('lesson_show', ['id' => $lesson->getId()]);
    }
}