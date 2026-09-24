<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AppointmentResource
{
    private const DEFAULT_COLORS = [
        '#4b86b0', '#9664b3', '#28887d', '#bb7042',
        '#c05283', '#5477b9', '#6f9148', '#ae5d5d',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 20)]
    private string $kind = 'person';

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $color = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedUser = null;

    public function __construct(Tenant $tenant, string $name, string $kind = 'person')
    {
        $this->tenant = $tenant;
        $this->name = $name;
        $this->kind = $kind;
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getName(): string { return $this->name; }
    public function getAssignedUser(): ?User { return $this->assignedUser; }
    public function setAssignedUser(?User $user): void { $this->assignedUser = $user; }
    public function isActive(): bool { return $this->isActive; }
    public function getColor(): string { return $this->color ?? self::DEFAULT_COLORS[($this->id ?? 0) % count(self::DEFAULT_COLORS)]; }
    public function setColor(string $color): void
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new \InvalidArgumentException('Ungültige Farbe.');
        }
        $this->color = strtolower($color);
    }

    public function update(string $name, string $kind, bool $active): void
    {
        $this->name = $name;
        $this->kind = $kind;
        $this->isActive = $active;
    }
}
