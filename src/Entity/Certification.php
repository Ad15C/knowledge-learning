<?php

namespace App\Entity;

use App\Repository\CertificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CertificationRepository::class)]
class Certification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'certifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Cursus::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cursus $cursus = null;

    #[ORM\ManyToOne(targetEntity: Theme::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Theme $theme = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $issuedAt = null;

    #[ORM\Column(length: 255)]
    private ?string $certificateCode = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    public function __construct()
    {
        $this->issuedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getCursus(): ?Cursus { return $this->cursus; }
    public function setCursus(?Cursus $cursus): static { $this->cursus = $cursus; return $this; }

    public function getTheme(): ?Theme { return $this->theme; }
    public function setTheme(?Theme $theme): static { $this->theme = $theme; return $this; }

    public function getIssuedAt(): ?\DateTimeInterface { return $this->issuedAt; }
    public function setIssuedAt(\DateTimeInterface $issuedAt): static { $this->issuedAt = $issuedAt; return $this; }

    public function getCertificateCode(): ?string { return $this->certificateCode; }
    public function setCertificateCode(string $certificateCode): static { $this->certificateCode = $certificateCode; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
}
