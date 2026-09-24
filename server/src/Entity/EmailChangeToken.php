<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'email_change_token')]
#[ORM\Index(name: 'idx_email_change_token_hash', columns: ['token_hash'])]
class EmailChangeToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 180)]
    private string $newEmail;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(User $user, Tenant $tenant, string $newEmail, string $tokenHash)
    {
        $this->user = $user;
        $this->tenant = $tenant;
        $this->newEmail = mb_strtolower($newEmail);
        $this->tokenHash = $tokenHash;
        $this->requestedAt = new \DateTimeImmutable();
        $this->expiresAt = $this->requestedAt->modify('+24 hours');
    }

    public function getUser(): User { return $this->user; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getNewEmail(): string { return $this->newEmail; }
    public function getRequestedAt(): \DateTimeImmutable { return $this->requestedAt; }
    public function isUsable(): bool { return $this->usedAt === null && $this->expiresAt > new \DateTimeImmutable(); }
    public function markUsed(): void { $this->usedAt = new \DateTimeImmutable(); }
}
