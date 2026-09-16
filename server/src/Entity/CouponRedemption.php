<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'coupon_redemption')]
#[ORM\Index(columns: ['tenant_id', 'customer_id', 'status'], name: 'idx_coupon_redemption_customer')]
#[ORM\Index(columns: ['batch_id'], name: 'idx_coupon_redemption_batch')]
class CouponRedemption
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Coupon $coupon;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $customer;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $validUntil;

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(length: 32)]
    private string $batchId;

    public function __construct(Tenant $tenant, Coupon $coupon, User $customer, string $batchId, ?\DateTimeImmutable $createdAt = null)
    {
        $this->tenant = $tenant;
        $this->coupon = $coupon;
        $this->customer = $customer;
        $this->batchId = $batchId;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->validUntil = $this->createdAt->modify('+5 minutes');
    }

    public function getId(): ?int { return $this->id; }
    public function getBatchId(): string { return $this->batchId; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getCoupon(): Coupon { return $this->coupon; }
    public function getCustomer(): User { return $this->customer; }
    public function getValidUntil(): \DateTimeImmutable { return $this->validUntil; }
    public function isRedeemed(): bool { return $this->status === 'active'; }
    public function isActive(): bool { return $this->isRedeemed() && $this->validUntil > new \DateTimeImmutable(); }
    public function cancel(): void { $this->status = 'cancelled'; }
}
