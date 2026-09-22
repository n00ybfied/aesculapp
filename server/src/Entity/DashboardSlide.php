<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'dashboard_slide')]
#[ORM\Index(columns: ['tenant_id', 'position'], name: 'idx_dashboard_slide_tenant_position')]
class DashboardSlide
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 255)]
    private string $imagePath;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $linkUrl;

    #[ORM\Column]
    private int $position;

    public function __construct(Tenant $tenant, string $imagePath, ?string $linkUrl, int $position)
    {
        $this->tenant = $tenant;
        $this->imagePath = $imagePath;
        $this->linkUrl = $linkUrl;
        $this->position = $position;
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getImagePath(): string { return $this->imagePath; }
    public function getLinkUrl(): ?string { return $this->linkUrl; }
    public function getPosition(): int { return $this->position; }
    public function setImagePath(string $imagePath): void { $this->imagePath = $imagePath; }
    public function setLinkUrl(?string $linkUrl): void { $this->linkUrl = $linkUrl; }
    public function setPosition(int $position): void { $this->position = $position; }
}
