<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'prize_game_spin')]
#[ORM\UniqueConstraint(name: 'uniq_prize_game_spin_request', columns: ['game_id', 'customer_id', 'request_id'])]
#[ORM\UniqueConstraint(name: 'uniq_prize_game_coupon_code', columns: ['coupon_code'])]
#[ORM\Index(columns: ['game_id', 'customer_id', 'created_at'], name: 'idx_prize_game_spin_customer')]
#[ORM\Index(columns: ['redemption_batch_id'], name: 'idx_prize_game_spin_redemption_batch')]
class PrizeGameSpin
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PrizeGame $game;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $customer;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PrizeGamePrize $prize;

    #[ORM\Column(length: 64)]
    private string $requestId;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $prizeTitle;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $prizeKind;

    #[ORM\Column]
    private int $points = 0;

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $couponCode = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $redeemedAt = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $redemptionBatchId = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $redemptionValidUntil = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $redeemedBy = null;

    public function __construct(PrizeGame $game, User $customer, ?PrizeGamePrize $prize, string $requestId, ?string $couponCode)
    {
        $this->game = $game;
        $this->customer = $customer;
        $this->prize = $prize;
        $this->requestId = $requestId;
        $this->createdAt = new \DateTimeImmutable();
        $this->prizeTitle = $prize?->getTitle();
        $this->prizeKind = $prize?->getKind();
        $this->points = $prize?->getPoints() ?? 0;
        $this->couponCode = $couponCode;
    }

    public function redeem(User $staff): void
    {
        $this->redeemedAt = new \DateTimeImmutable();
        $this->redeemedBy = $staff;
    }

    public function startPresentation(string $batchId, \DateTimeImmutable $now, int $durationMinutes = 5): void
    {
        $this->redeemedAt = $now;
        $this->redemptionBatchId = $batchId;
        $this->redemptionValidUntil = $now->modify('+'.$durationMinutes.' minutes');
    }

    public function cancelPresentation(): void
    {
        $this->redeemedAt = null;
        $this->redemptionBatchId = null;
        $this->redemptionValidUntil = null;
    }

    public function isPresentationActive(): bool
    {
        return $this->redemptionBatchId !== null && $this->redemptionValidUntil !== null && $this->redemptionValidUntil > new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getGame(): PrizeGame { return $this->game; }
    public function getCustomer(): User { return $this->customer; }
    public function getPrize(): ?PrizeGamePrize { return $this->prize; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPrizeTitle(): ?string { return $this->prizeTitle; }
    public function getPrizeKind(): ?string { return $this->prizeKind; }
    public function getPoints(): int { return $this->points; }
    public function getCouponCode(): ?string { return $this->couponCode; }
    public function getRedeemedAt(): ?\DateTimeImmutable { return $this->redeemedAt; }
    public function getRedeemedBy(): ?User { return $this->redeemedBy; }
    public function getRedemptionBatchId(): ?string { return $this->redemptionBatchId; }
    public function getRedemptionValidUntil(): ?\DateTimeImmutable { return $this->redemptionValidUntil; }
}
