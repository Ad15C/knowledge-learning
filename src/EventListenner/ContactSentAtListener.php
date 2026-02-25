<?php

namespace App\EventListener;

use App\Entity\Contact;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bridge\Doctrine\Attribute\AsDoctrineListener;

#[AsDoctrineListener(event: Events::prePersist)]
class ContactSentAtListener
{
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Contact) {
            return;
        }

        if ($entity->getSentAt() === null) {
            $entity->setSentAt(new \DateTimeImmutable());
        }
    }
}