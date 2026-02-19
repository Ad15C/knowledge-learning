<?php

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LessonRepository::class)]
class Lesson
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 2)]
    private ?float $price = null;

    #[ORM\ManyToOne(targetEntity: Cursus::class, inversedBy: 'lessons')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cursus $cursus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $fiche = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $videoUrl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: 'boolean')]
    private bool $userHasCompleted = false;

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getPrice(): ?float { return $this->price; }
    public function setPrice(float $price): static { $this->price = $price; return $this; }

    public function getCursus(): ?Cursus { return $this->cursus; }
    public function setCursus(?Cursus $cursus): static { $this->cursus = $cursus; return $this; }

    public function getFiche(): ?string { return $this->fiche; }
    public function setFiche(?string $fiche): static { $this->fiche = $fiche; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    public function getVideoUrl(): ?string { return $this->videoUrl; }
    public function setVideoUrl(?string $videoUrl): static { $this->videoUrl = $videoUrl; return $this; }

    public function getUserHasCompleted(): bool { return $this->userHasCompleted; }
    public function setUserHasCompleted(bool $completed): static { $this->userHasCompleted = $completed; return $this; }
}
