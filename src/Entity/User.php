<?php

namespace App\Entity;

use App\Repository\UserRepository;
use App\Entity\Lesson;
use App\Entity\LessonValidated;
use App\Entity\Purchase;
use App\Entity\Certification;
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
    #[Assert\NotBlank(message: 'This value should not be blank.')]
    #[Assert\Email(
        message: 'This value is not a valid email address.',
        mode: 'html5'
    )]
    private ?string $email = null;

    /**
     * Rôles stockés en base (sans ROLE_USER automatique).
     * Dans ton app: on stocke uniquement [] ou ["ROLE_ADMIN"].
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    // Non persisté
    private ?string $plainPassword = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'This value should not be blank.')]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'This value should not be blank.')]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $lastName = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $verificationToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $verificationTokenExpiresAt = null;

    // nullable pour migration / legacy DB
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    // --- Relations ---
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: LessonValidated::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $lessonValidated;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Purchase::class, orphanRemoval: true)]
    private Collection $purchases;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Certification::class, orphanRemoval: true)]
    private Collection $certifications;

    #[ORM\ManyToMany(targetEntity: Lesson::class)]
    #[ORM\JoinTable(name: 'user_completed_lessons')]
    private Collection $completedLessons;

    public function __construct()
    {
        $this->lessonValidated = new ArrayCollection();
        $this->purchases = new ArrayCollection();
        $this->certifications = new ArrayCollection();
        $this->completedLessons = new ArrayCollection();

        // Valeurs par défaut
        $this->roles = [];
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- ID & Email ---
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    // --- Roles ---

    /**
     * Rôles utilisés par Symfony Security.
     * Ajoute toujours ROLE_USER en runtime.
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * Setter utilisé par Symfony/Form.
     * On normalise pour ne stocker que [] ou ['ROLE_ADMIN'] en base.
     */
    public function setRoles(array $roles): self
    {
        return $this->setStoredRoles($roles);
    }

    /**
     * Rôles stockés en base (sans ROLE_USER automatique).
     */
    public function getStoredRoles(): array
    {
        return $this->roles ?? [];
    }

    /**
     * Setter "propre" : stocke uniquement ROLE_ADMIN si présent, sinon [].
     */
    public function setStoredRoles(array $roles): self
    {
        $roles = array_values(array_unique(array_filter($roles, fn($r) => $r !== 'ROLE_USER')));

        $this->roles = in_array('ROLE_ADMIN', $roles, true) ? ['ROLE_ADMIN'] : [];
        return $this;
    }

    // --- Password ---
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Symfony l'appelle après authentification.
     */
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    // --- Plain password (NON persisté) ---
    public function setPlainPassword(?string $password): self
    {
        $this->plainPassword = $password;
        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    // --- User Info ---
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    // --- Verification Token ---
    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(?string $token): self
    {
        $this->verificationToken = $token;
        return $this;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->verificationTokenExpiresAt;
    }

    public function setVerificationTokenExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->verificationTokenExpiresAt = $expiresAt;
        return $this;
    }

    // --- Created & Archived ---
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $dt): self
    {
        $this->createdAt = $dt;
        return $this;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $dt): self
    {
        $this->archivedAt = $dt;
        return $this;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function isActive(): bool
    {
        return $this->archivedAt === null;
    }

    // --- LessonValidated ---
    /** @return Collection<int, LessonValidated> */
    public function getLessonValidated(): Collection
    {
        return $this->lessonValidated;
    }

    public function addLessonValidated(LessonValidated $lessonValidated): self
    {
        if (!$this->lessonValidated->contains($lessonValidated)) {
            $this->lessonValidated->add($lessonValidated);
            $lessonValidated->setUser($this);
        }
        return $this;
    }

    public function removeLessonValidated(LessonValidated $lessonValidated): self
    {
        if ($this->lessonValidated->removeElement($lessonValidated) && $lessonValidated->getUser() === $this) {
            $lessonValidated->setUser(null);
        }
        return $this;
    }

    // --- Purchases ---
    /** @return Collection<int, Purchase> */
    public function getPurchases(): Collection
    {
        return $this->purchases;
    }

    public function addPurchase(Purchase $purchase): self
    {
        if (!$this->purchases->contains($purchase)) {
            $this->purchases->add($purchase);
            $purchase->setUser($this);
        }
        return $this;
    }

    public function removePurchase(Purchase $purchase): self
    {
        if ($this->purchases->removeElement($purchase) && $purchase->getUser() === $this) {
            $purchase->setUser(null);
        }
        return $this;
    }

    // --- Certifications ---
    /** @return Collection<int, Certification> */
    public function getCertifications(): Collection
    {
        return $this->certifications;
    }

    public function addCertification(Certification $certification): self
    {
        if (!$this->certifications->contains($certification)) {
            $this->certifications->add($certification);
            $certification->setUser($this);
        }
        return $this;
    }

    public function removeCertification(Certification $certification): self
    {
        if ($this->certifications->removeElement($certification) && $certification->getUser() === $this) {
            $certification->setUser(null);
        }
        return $this;
    }

    // --- Completed Lessons (ManyToMany) ---
    /** @return Collection<int, Lesson> */
    public function getCompletedLessons(): Collection
    {
        return $this->completedLessons;
    }

    public function addCompletedLesson(Lesson $lesson): self
    {
        if (!$this->completedLessons->contains($lesson)) {
            $this->completedLessons->add($lesson);
        }
        return $this;
    }

    public function removeCompletedLesson(Lesson $lesson): self
    {
        $this->completedLessons->removeElement($lesson);
        return $this;
    }
}