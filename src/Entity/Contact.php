<?php

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez renseigner votre nom.')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Le nom doit faire au moins {{ limit }} caractères.')]
    private ?string $fullname = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Veuillez renseigner votre e-mail.')]
    #[Assert\Email(message: 'Veuillez renseigner un e-mail valide.')]
    #[Assert\Length(max: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Veuillez choisir un sujet.')]
    #[Assert\Length(max: 100)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Veuillez écrire un message.')]
    #[Assert\Length(min: 10, minMessage: 'Le message doit faire au moins {{ limit }} caractères.')]
    private ?string $message = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $handled = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    public function getId(): ?int { return $this->id; }

    public function getFullname(): ?string { return $this->fullname; }
    public function setFullname(string $fullname): static { $this->fullname = $fullname; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(string $subject): static { $this->subject = $subject; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }

    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(\DateTimeImmutable $sentAt): static { $this->sentAt = $sentAt; return $this; }

    public function getReadAt(): ?\DateTimeImmutable { return $this->readAt; }

    public function markRead(): static
    {
        $this->readAt = new \DateTimeImmutable();
        return $this;
    }

    public function markUnread(): static
    {
        $this->readAt = null;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getHandledAt(): ?\DateTimeImmutable { return $this->handledAt; }

    public function isHandled(): bool
    {
        return $this->handled;
    }

    public function setHandled(bool $handled): static
    {
        $this->handled = $handled;

        if ($handled && $this->handledAt === null) {
            $this->handledAt = new \DateTimeImmutable();
        }

        if (!$handled) {
            $this->handledAt = null;
        }

        return $this;
    }

    public function setHandledAt(?\DateTimeImmutable $handledAt): static
    {
        $this->handledAt = $handledAt;
        $this->handled = ($handledAt !== null);
        return $this;
    }

    public function getStatus(): string
    {
        if ($this->handled || $this->handledAt !== null) {
            return 'handled';
        }
        if ($this->isRead()) {
            return 'read';
        }
        return 'unread';
    }

    public function getSubjectLabel(): string
    {
        return match ($this->subject) {
            'theme' => 'Question sur un thème',
            'cursus' => 'Question sur un cursus',
            'lesson' => 'Question sur une leçon',
            'payment' => 'Question sur le paiement',
            'validation' => 'Question sur la validation du cours',
            'certification' => 'Question sur la certification',
            'registration' => 'Question sur l’inscription',
            'login' => 'Question sur la connexion',
            'other' => 'Autre question',
            default => $this->subject ?? '',
        };
    }
}