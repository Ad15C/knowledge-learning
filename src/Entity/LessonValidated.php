<?php

namespace App\Entity;

use App\Repository\LessonValidatedRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use App\Entity\Lesson;
use App\Entity\PurchaseItem;

#[ORM\Entity(repositoryClass: LessonValidatedRepository::class)]
#[ORM\Table(name: "lesson_validated")]
#[ORM\UniqueConstraint(name: 'user_lesson_unique', columns: ['user_id', 'lesson_id'])]
class LessonValidated
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "lessonValidated")]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Lesson::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lesson $lesson = null;

    #[ORM\ManyToOne(targetEntity: PurchaseItem::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?PurchaseItem $purchaseItem = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $completed = true;

    public function __construct()
    {
        $this->validatedAt = new \DateTimeImmutable();
        $this->completed = true;
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getLesson(): ?Lesson { return $this->lesson; }
    public function setLesson(?Lesson $lesson): static { $this->lesson = $lesson; return $this; }

    public function getPurchaseItem(): ?PurchaseItem { return $this->purchaseItem; }
    public function setPurchaseItem(?PurchaseItem $purchaseItem): static
    {
        $this->purchaseItem = $purchaseItem;
        return $this;
    }

    public function getValidatedAt(): ?\DateTimeInterface { return $this->validatedAt; }

    public function isCompleted(): bool { return $this->completed; }

    public function markCompleted(): static
    {
        $this->completed = true;
        $this->validatedAt = new \DateTimeImmutable();
        return $this;
    }
}