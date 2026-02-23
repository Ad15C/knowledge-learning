<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "This value should not be blank.")]
    #[Assert\Email(message: "This value is not a valid email address.")]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "This value should not be blank.")]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "This value should not be blank.")]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $lastName = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verificationToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $verificationTokenExpiresAt = null;

    // --- Relations ---
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: LessonValidated::class, cascade: ["remove"], orphanRemoval: true)]
    private Collection $lessonValidated;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Purchase::class, orphanRemoval: true)]
    private Collection $purchases;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Certification::class, orphanRemoval: true)]
    private Collection $certifications;

    #[ORM\ManyToMany(targetEntity: Lesson::class)]
    #[ORM\JoinTable(name: "user_completed_lessons")]
    private Collection $completedLessons;

    public function __construct()
    {
        $this->lessonValidated = new ArrayCollection();
        $this->purchases = new ArrayCollection();
        $this->certifications = new ArrayCollection();
        $this->completedLessons = new ArrayCollection();
        $this->roles = [];
    }

    // --- ID & Email ---
    public function getId(): ?int { return $this->id; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }
    public function getUserIdentifier(): string { return (string)$this->email; }

    // --- Roles ---
    public function getRoles(): array { $roles = $this->roles; $roles[] = 'ROLE_USER'; return array_unique($roles); }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    // --- Password ---
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }
    #[\Deprecated] public function eraseCredentials(): void {}

    // --- User Info ---
    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $firstName): self { $this->firstName = $firstName; return $this; }
    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $lastName): self { $this->lastName = $lastName; return $this; }
    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): static { $this->isVerified = $isVerified; return $this; }

    // --- Verification Token ---
    public function getVerificationToken(): ?string { return $this->verificationToken; }
    public function setVerificationToken(?string $token): static { $this->verificationToken = $token; return $this; }
    public function getVerificationTokenExpiresAt(): ?\DateTimeInterface { return $this->verificationTokenExpiresAt; }
    public function setVerificationTokenExpiresAt(?\DateTimeInterface $expiresAt): static { $this->verificationTokenExpiresAt = $expiresAt; return $this; }

    // --- LessonValidated ---
    /** @return Collection<int, LessonValidated> */
    public function getLessonValidated(): Collection { return $this->lessonValidated; }
    public function addLessonValidated(LessonValidated $lessonValidated): static
    {
        if (!$this->lessonValidated->contains($lessonValidated)) {
            $this->lessonValidated->add($lessonValidated);
            $lessonValidated->setUser($this);
        }
        return $this;
    }
    public function removeLessonValidated(LessonValidated $lessonValidated): static
    {
        if ($this->lessonValidated->removeElement($lessonValidated) && $lessonValidated->getUser() === $this) {
            $lessonValidated->setUser(null);
        }
        return $this;
    }

    // --- Purchases ---
    /** @return Collection<int, Purchase> */
    public function getPurchases(): Collection { return $this->purchases; }
    public function addPurchase(Purchase $purchase): static
    {
        if (!$this->purchases->contains($purchase)) {
            $this->purchases->add($purchase);
            $purchase->setUser($this);
        }
        return $this;
    }
    public function removePurchase(Purchase $purchase): static
    {
        if ($this->purchases->removeElement($purchase) && $purchase->getUser() === $this) {
            $purchase->setUser(null);
        }
        return $this;
    }

    // --- Certifications ---
    /** @return Collection<int, Certification> */
    public function getCertifications(): Collection { return $this->certifications; }
    public function addCertification(Certification $certification): static
    {
        if (!$this->certifications->contains($certification)) {
            $this->certifications->add($certification);
            $certification->setUser($this);
        }
        return $this;
    }
    public function removeCertification(Certification $certification): static
    {
        if ($this->certifications->removeElement($certification) && $certification->getUser() === $this) {
            $certification->setUser(null);
        }
        return $this;
    }

    // --- Completed Lessons (ManyToMany) ---
    /** @return Collection<int, Lesson> */
    public function getCompletedLessons(): Collection { return $this->completedLessons; }
    public function addCompletedLesson(Lesson $lesson): static
    {
        if (!$this->completedLessons->contains($lesson)) {
            $this->completedLessons->add($lesson);
        }
        return $this;
    }
    public function removeCompletedLesson(Lesson $lesson): static
    {
        $this->completedLessons->removeElement($lesson);
        return $this;
    }
}