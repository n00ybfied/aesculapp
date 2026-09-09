<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'loyalty_receipt_redemption')]
#[ORM\UniqueConstraint(name: 'uniq_loyalty_receipt_tenant_hash', columns: ['tenant_id', 'qr_hash'])]
class LoyaltyReceiptRedemption
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
    private PointAccount $account;

    #[ORM\Column(name: 'qr_hash', length: 64)]
    private string $qrHash;

    #[ORM\Column(length: 160)]
    private string $receiptNumber;

    #[ORM\Column]
    private \DateTimeImmutable $receiptIssuedAt;

    #[ORM\Column]
    private int $eligibleCents;

    #[ORM\Column]
    private int $creditedPoints;

    #[ORM\Column]
    private \DateTimeImmutable $redeemedAt;

    public function __construct(Tenant $tenant, PointAccount $account, string $qrHash, string $receiptNumber, \DateTimeImmutable $receiptIssuedAt, int $eligibleCents, int $creditedPoints)
    {
        $this->tenant = $tenant;
        $this->account = $account;
        $this->qrHash = $qrHash;
        $this->receiptNumber = $receiptNumber;
        $this->receiptIssuedAt = $receiptIssuedAt;
        $this->eligibleCents = $eligibleCents;
        $this->creditedPoints = $creditedPoints;
        $this->redeemedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
}
