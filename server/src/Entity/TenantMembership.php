<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\TenantMembershipRepository::class)]
#[ORM\Table(name: 'tenant_membership')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_membership', columns: ['tenant_id', 'user_id'])]
class TenantMembership
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
    private User $user;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $newsletterEnabled = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $chatPushEnabled = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $rewardPushEnabled = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $newsPushEnabled = false;

    /**
     * @param list<string> $roles
     */
    public function __construct(Tenant $tenant, User $user, array $roles = ['ROLE_CUSTOMER'])
    {
        $this->tenant = $tenant;
        $this->user = $user;
        $this->roles = $roles;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): void
    {
        $this->roles = array_values(array_unique($roles));
    }

    public function isNewsletterEnabled(): bool { return $this->newsletterEnabled; }
    public function setNewsletterEnabled(bool $value): void { $this->newsletterEnabled = $value; }
    public function isChatPushEnabled(): bool { return $this->chatPushEnabled; }
    public function setChatPushEnabled(bool $value): void { $this->chatPushEnabled = $value; }
    public function isRewardPushEnabled(): bool { return $this->rewardPushEnabled; }
    public function setRewardPushEnabled(bool $value): void { $this->rewardPushEnabled = $value; }
    public function isNewsPushEnabled(): bool { return $this->newsPushEnabled; }
    public function setNewsPushEnabled(bool $value): void { $this->newsPushEnabled = $value; }
}
