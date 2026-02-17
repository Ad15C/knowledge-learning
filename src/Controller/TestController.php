<?php 

// src/Controller/TestController.php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/delete-test-user', name: 'delete_test_user')]
    public function deleteTestUser(EntityManagerInterface $em)
    {
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        if ($user) {
            $em->remove($user);
            $em->flush();
            return $this->json(['status' => 'Utilisateur supprimé']);
        }
        return $this->json(['status' => 'Utilisateur non trouvé']);
    }
}
