<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'prize_game_prize')]
#[ORM\Index(columns: ['game_id'], name: 'idx_prize_game_prize_game')]
class PrizeGamePrize
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PrizeGame $game;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column(length: 40)]
    private string $wheelLabel;

    #[ORM\Column(length: 20)]
    private string $kind;

    #[ORM\Column]
    private int $points;

    #[ORM\Column]
    private int $probabilityBp;

    #[ORM\Column(nullable: true)]
    private ?int $stockLimit;

    #[ORM\Column]
    private int $awardedCount = 0;

    #[ORM\Column]
    private int $position;

    public function __construct(PrizeGame $game, string $title, string $wheelLabel, string $kind, int $points, int $probabilityBp, ?int $stockLimit, int $position)
    {
        $this->game = $game;
        $this->update($title, $wheelLabel, $kind, $points, $probabilityBp, $stockLimit, $position);
    }

    public function update(string $title, string $wheelLabel, string $kind, int $points, int $probabilityBp, ?int $stockLimit, int $position): void
    {
        $this->title = $title;
        $this->wheelLabel = $wheelLabel;
        $this->kind = $kind;
        $this->points = $points;
        $this->probabilityBp = $probabilityBp;
        $this->stockLimit = $stockLimit;
        $this->position = $position;
    }

    public function award(): void { ++$this->awardedCount; }
    public function isExhausted(): bool { return $this->stockLimit !== null && $this->awardedCount >= $this->stockLimit; }
    public function getId(): ?int { return $this->id; }
    public function getGame(): PrizeGame { return $this->game; }
    public function getTitle(): string { return $this->title; }
    public function getWheelLabel(): string { return $this->wheelLabel; }
    public function getKind(): string { return $this->kind; }
    public function getPoints(): int { return $this->points; }
    public function getProbabilityBp(): int { return $this->probabilityBp; }
    public function getStockLimit(): ?int { return $this->stockLimit; }
    public function getAwardedCount(): int { return $this->awardedCount; }
    public function getPosition(): int { return $this->position; }
}
