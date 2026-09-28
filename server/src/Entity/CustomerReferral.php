<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customer_referral')]
#[ORM\UniqueConstraint(name: 'uniq_customer_referral_invitee', columns: ['tenant_id', 'invitee_id'])]
class CustomerReferral
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TenantMembership $inviter;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $invitee;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $awardedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $awardedPoints = 0;

    public function __construct(Tenant $tenant, TenantMembership $inviter, User $invitee)
    {
        $this->tenant = $tenant;
        $this->inviter = $inviter;
        $this->invitee = $invitee;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getInviter(): TenantMembership { return $this->inviter; }
    public function getAwardedAt(): ?\DateTimeImmutable { return $this->awardedAt; }
    public function getAwardedPoints(): int { return $this->awardedPoints; }
}
