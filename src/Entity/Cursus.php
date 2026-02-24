<?php

namespace App\Entity;

use App\Repository\CursusRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CursusRepository::class)]
class Cursus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Theme::class, inversedBy: 'cursus')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Theme $theme = null;

    #[ORM\OneToMany(mappedBy: 'cursus', targetEntity: Lesson::class, orphanRemoval: true)]
    private Collection $lessons;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;


    public function __construct()
    {
        $this->lessons = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getPrice(): ?float { return $this->price !== null ? (float)$this->price : null; }
    public function setPrice(float $price): static { $this->price = (string)$price; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }


    public function getTheme(): ?Theme { return $this->theme; }
    public function setTheme(?Theme $theme): static { $this->theme = $theme; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    /** @return Collection<int, Lesson> */
    public function getLessons(): Collection { return $this->lessons; }

    public function addLesson(Lesson $lesson): static
    {
        if (!$this->lessons->contains($lesson)) {
            $this->lessons->add($lesson);
            $lesson->setCursus($this);
        }
        return $this;
    }

}
