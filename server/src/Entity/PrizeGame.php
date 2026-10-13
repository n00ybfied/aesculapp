<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'prize_game')]
#[ORM\Index(columns: ['tenant_id', 'visible', 'available_from', 'available_until'], name: 'idx_prize_game_visibility')]
class PrizeGame
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 180)]
    private string $title;

    #[ORM\Column]
    private bool $visible = false;

    #[ORM\Column]
    private \DateTimeImmutable $availableFrom;

    #[ORM\Column]
    private \DateTimeImmutable $availableUntil;

    #[ORM\Column(length: 20)]
    private string $participationMode;

    #[ORM\Column]
    private int $spinLimit;

    #[ORM\Column]
    private bool $noWinEnabled;

    #[ORM\Column(type: 'text')]
    private string $soldOutText;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $soldOutAt = null;

    public function __construct(Tenant $tenant, string $title, \DateTimeImmutable $from, \DateTimeImmutable $until)
    {
        $this->tenant = $tenant;
        $this->title = $title;
        $this->availableFrom = $from;
        $this->availableUntil = $until;
        $this->participationMode = 'once';
        $this->spinLimit = 1;
        $this->noWinEnabled = false;
        $this->soldOutText = 'Alle Gewinne sind vergeben. Vielen Dank fürs Mitmachen!';
    }

    public function configure(string $title, bool $visible, \DateTimeImmutable $from, \DateTimeImmutable $until, string $mode, int $limit, bool $noWin, string $soldOutText): void
    {
        $this->title = $title;
        $this->visible = $visible;
        $this->availableFrom = $from;
        $this->availableUntil = $until;
        $this->participationMode = $mode;
        $this->spinLimit = $limit;
        $this->noWinEnabled = $noWin;
        $this->soldOutText = $soldOutText;
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getTitle(): string { return $this->title; }
    public function isVisible(): bool { return $this->visible; }
    public function getAvailableFrom(): \DateTimeImmutable { return $this->availableFrom; }
    public function getAvailableUntil(): \DateTimeImmutable { return $this->availableUntil; }
    public function getParticipationMode(): string { return $this->participationMode; }
    public function getSpinLimit(): int { return $this->spinLimit; }
    public function isNoWinEnabled(): bool { return $this->noWinEnabled; }
    public function getSoldOutText(): string { return $this->soldOutText; }
    public function getSoldOutAt(): ?\DateTimeImmutable { return $this->soldOutAt; }
    public function markSoldOut(\DateTimeImmutable $at): void { $this->soldOutAt ??= $at; }
    public function clearSoldOut(): void { $this->soldOutAt = null; }
}
