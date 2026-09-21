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

    #[ORM\Column(options: ['default' => false])]
    private bool $medicationPushEnabled = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $appointmentPushEnabled = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $familyPushEnabled = true;

    #[ORM\Column(length: 5, options: ['default' => '08:00'])]
    private string $morningReminderTime = '08:00';

    #[ORM\Column(length: 5, options: ['default' => '12:00'])]
    private string $noonReminderTime = '12:00';

    #[ORM\Column(length: 5, options: ['default' => '18:00'])]
    private string $eveningReminderTime = '18:00';

    #[ORM\Column(length: 5, options: ['default' => '22:00'])]
    private string $nightReminderTime = '22:00';
    #[ORM\Column(options: ['default' => true])] private bool $footerHomeEnabled = true;
    #[ORM\Column(options: ['default' => true])] private bool $footerChatEnabled = true;
    #[ORM\Column(options: ['default' => true])] private bool $footerRewardsEnabled = true;
    #[ORM\Column(options: ['default' => true])] private bool $footerWebsiteEnabled = true;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
    public function isMedicationPushEnabled(): bool { return $this->medicationPushEnabled; }
    public function setMedicationPushEnabled(bool $value): void { $this->medicationPushEnabled = $value; }
    public function isAppointmentPushEnabled(): bool { return $this->appointmentPushEnabled; }
    public function setAppointmentPushEnabled(bool $value): void { $this->appointmentPushEnabled = $value; }
    public function isFamilyPushEnabled(): bool { return $this->familyPushEnabled; }
    public function setFamilyPushEnabled(bool $value): void { $this->familyPushEnabled = $value; }
    public function getMorningReminderTime(): string { return $this->morningReminderTime; }
    public function setMorningReminderTime(string $value): void { $this->morningReminderTime = $value; }
    public function getNoonReminderTime(): string { return $this->noonReminderTime; }
    public function setNoonReminderTime(string $value): void { $this->noonReminderTime = $value; }
    public function getEveningReminderTime(): string { return $this->eveningReminderTime; }
    public function setEveningReminderTime(string $value): void { $this->eveningReminderTime = $value; }
    public function getNightReminderTime(): string { return $this->nightReminderTime; }
    public function setNightReminderTime(string $value): void { $this->nightReminderTime = $value; }
    public function isFooterHomeEnabled(): bool { return $this->footerHomeEnabled; }
    public function setFooterHomeEnabled(bool $value): void { $this->footerHomeEnabled = $value; }
    public function isFooterChatEnabled(): bool { return $this->footerChatEnabled; }
    public function setFooterChatEnabled(bool $value): void { $this->footerChatEnabled = $value; }
    public function isFooterRewardsEnabled(): bool { return $this->footerRewardsEnabled; }
    public function setFooterRewardsEnabled(bool $value): void { $this->footerRewardsEnabled = $value; }
    public function isFooterWebsiteEnabled(): bool { return $this->footerWebsiteEnabled; }
    public function setFooterWebsiteEnabled(bool $value): void { $this->footerWebsiteEnabled = $value; }
}
