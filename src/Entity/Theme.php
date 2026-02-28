<?php

namespace App\Entity;

use App\Repository\ThemeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThemeRepository::class)]
class Theme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'theme', targetEntity: Cursus::class, orphanRemoval: true)]
    private Collection $cursus;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    public function __construct()
    {
        $this->cursus = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    /** @return Collection<int, Cursus> */
    public function getCursus(): Collection { return $this->cursus; }

    public function addCursus(Cursus $cursus): static
    {
        if (!$this->cursus->contains($cursus)) {
            $this->cursus->add($cursus);
            $cursus->setTheme($this);
        }
        return $this;
    }

    public function removeCursus(Cursus $cursus): static
    {
        if ($this->cursus->removeElement($cursus) && $cursus->getTheme() === $this) {
            $cursus->setTheme(null);
        }
        return $this;
    }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
}