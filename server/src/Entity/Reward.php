<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reward')]
class Reward
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(length: 200)]
    private string $subtitle;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 255)]
    private string $imagePath;

    #[ORM\Column]
    private int $requiredPoints;

    #[ORM\Column(options: ['default' => true])]
    private bool $isVisible;

    public function __construct(Tenant $tenant, string $title, string $subtitle, string $description, string $imagePath, int $requiredPoints, bool $isVisible)
    {
        $this->tenant = $tenant;
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->description = $description;
        $this->imagePath = $imagePath;
        $this->requiredPoints = $requiredPoints;
        $this->isVisible = $isVisible;
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getTitle(): string { return $this->title; }
    public function getSubtitle(): string { return $this->subtitle; }
    public function getDescription(): string { return $this->description; }
    public function getImagePath(): string { return $this->imagePath; }
    public function getRequiredPoints(): int { return $this->requiredPoints; }
    public function isVisible(): bool { return $this->isVisible; }
    public function setVisible(bool $isVisible): void { $this->isVisible = $isVisible; }
}
