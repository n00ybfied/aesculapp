<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StaffInvitationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StaffInvitationRepository::class)]
#[ORM\Table(name: 'staff_invitation')]
#[ORM\Index(name: 'idx_staff_invitation_token_hash', columns: ['token_hash'])]
class StaffInvitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 160)]
    private string $displayName;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @param list<string> $roles */
    public function __construct(Tenant $tenant, string $email, string $displayName, array $roles, string $tokenHash)
    {
        $this->tenant = $tenant;
        $this->email = mb_strtolower(trim($email));
        $this->displayName = $displayName;
        $this->roles = $roles;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = new \DateTimeImmutable('+7 days');
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getEmail(): string { return $this->email; }
    public function getDisplayName(): string { return $this->displayName; }
    /** @return list<string> */
    public function getRoles(): array { return $this->roles; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function markAccepted(): void { $this->acceptedAt = new \DateTimeImmutable(); }
}
