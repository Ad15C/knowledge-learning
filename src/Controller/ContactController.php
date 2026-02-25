<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/contact')]
class ContactController extends AbstractController
{
    #[Route('/', name: 'contact_index')]
    public function index(Request $request, EntityManagerInterface $em, MailerInterface $mailer): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactFormType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $em->persist($contact);
            $em->flush();

            $email = (new Email())
                ->from('no-reply@ton-site.fr')
                ->to('admin@ton-site.fr')
                ->replyTo($contact->getEmail())
                ->subject('[Contact] ' . $contact->getSubject())
                ->text(
                    "Nom: " . $contact->getFullname() . "\n" .
                    "Email: " . $contact->getEmail() . "\n" .
                    "Sujet: " . $contact->getSubject() . "\n\n" .
                    $contact->getMessage()
                );

            try {
                $mailer->send($email);
                $this->addFlash('success', 'Votre message a été envoyé avec succès !');
            } catch (\Throwable $e) {
                $this->addFlash('warning', "Message enregistré, mais l'email n'a pas pu être envoyé.");
            }

            return $this->redirectToRoute('contact_index');
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView(),
        ]);
    }
}