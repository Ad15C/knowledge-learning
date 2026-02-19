<?php

namespace App\Entity;

use App\Repository\LessonValidatedRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LessonValidatedRepository::class)]
class LessonValidated
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lesson $lesson = null;

    #[ORM\ManyToOne(targetEntity: PurchaseItem::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?PurchaseItem $purchaseItem = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $validatedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $completed = false;

    public function __construct()
    {
        $this->validatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getLesson(): ?Lesson { return $this->lesson; }
    public function setLesson(?Lesson $lesson): static { $this->lesson = $lesson; return $this; }

    public function getPurchaseItem(): ?PurchaseItem { return $this->purchaseItem; }
    public function setPurchaseItem(?PurchaseItem $purchaseItem): static { $this->purchaseItem = $purchaseItem; return $this; }

    public function getValidatedAt(): ?\DateTime { return $this->validatedAt; }
    public function setValidatedAt(\DateTime $validatedAt): static { $this->validatedAt = $validatedAt; return $this; }

    public function isCompleted(): bool { return $this->completed; }
    public function setCompleted(bool $completed): static { $this->completed = $completed; return $this; }

    public function markCompleted(): static
    {
        $this->completed = true;
        $this->validatedAt = new \DateTime();
        return $this;
    }
}
