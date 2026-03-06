<?php

namespace App\DataFixtures;

use App\Entity\Contact;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ContactFixtures extends Fixture
{
    public const CONTACT_UNREAD_REF = 'contact_unread';
    public const CONTACT_READ_REF = 'contact_read';
    public const CONTACT_HANDLED_REF = 'contact_handled';
    public const CONTACT_HANDLED_PAYMENT_REF = 'contact_handled_payment';

    public function load(ObjectManager $manager): void
    {
        // CONTACT UNREAD
        $contactUnread = (new Contact())
            ->setFullname('Alice Martin')
            ->setEmail('alice.martin@example.com')
            ->setSubject('registration')
            ->setMessage('Bonjour, je souhaite avoir plus d’informations sur mon inscription.')
            ->setSentAt(new \DateTimeImmutable('-5 days'))
            ->setReadAt(null)
            ->setHandled(false);

        $manager->persist($contactUnread);
        $this->addReference(self::CONTACT_UNREAD_REF, $contactUnread);

        // CONTACT READ
        $contactRead = (new Contact())
            ->setFullname('Bob Dupont')
            ->setEmail('bob.dupont@example.com')
            ->setSubject('login')
            ->setMessage('Je rencontre un problème pour me connecter à mon espace personnel.')
            ->setSentAt(new \DateTimeImmutable('-4 days'))
            ->setReadAt(new \DateTimeImmutable('-3 days'))
            ->setHandled(false);

        $manager->persist($contactRead);
        $this->addReference(self::CONTACT_READ_REF, $contactRead);

        // CONTACT HANDLED
        $contactHandled = (new Contact())
            ->setFullname('Claire Bernard')
            ->setEmail('claire.bernard@example.com')
            ->setSubject('lesson')
            ->setMessage('Je voudrais savoir comment accéder à une leçon déjà achetée.')
            ->setSentAt(new \DateTimeImmutable('-3 days'))
            ->setReadAt(new \DateTimeImmutable('-2 days'))
            ->setHandledAt(new \DateTimeImmutable('-1 day'));

        $manager->persist($contactHandled);
        $this->addReference(self::CONTACT_HANDLED_REF, $contactHandled);

        // AUTRE CONTACT HANDLED AVEC AUTRE SUJET
        $contactHandledPayment = (new Contact())
            ->setFullname('David Leroy')
            ->setEmail('david.leroy@example.com')
            ->setSubject('payment')
            ->setMessage('J’ai une question concernant le paiement et la validation de ma commande.')
            ->setSentAt(new \DateTimeImmutable('-2 days'))
            ->setReadAt(new \DateTimeImmutable('-2 days'))
            ->setHandledAt(new \DateTimeImmutable('-1 day'));

        $manager->persist($contactHandledPayment);
        $this->addReference(self::CONTACT_HANDLED_PAYMENT_REF, $contactHandledPayment);

        $manager->flush();
    }
}