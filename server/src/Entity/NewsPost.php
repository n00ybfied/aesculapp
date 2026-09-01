<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'news_post')]
class NewsPost
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

    #[ORM\Column(length: 255)]
    private string $subtitle;

    #[ORM\Column(type: 'text')]
    private string $bodyHtml;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath;

    #[ORM\Column]
    private bool $isVisible;

    #[ORM\Column]
    private \DateTimeImmutable $publishedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $showFrom;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $showUntil;

    public function __construct(Tenant $tenant, string $title, string $subtitle, string $bodyHtml, ?string $imagePath, bool $isVisible, \DateTimeImmutable $publishedAt, ?\DateTimeImmutable $showFrom, ?\DateTimeImmutable $showUntil)
    {
        $this->tenant = $tenant;
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->bodyHtml = $bodyHtml;
        $this->imagePath = $imagePath;
        $this->isVisible = $isVisible;
        $this->publishedAt = $publishedAt;
        $this->showFrom = $showFrom;
        $this->showUntil = $showUntil;
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getTitle(): string { return $this->title; }
    public function getSubtitle(): string { return $this->subtitle; }
    public function getBodyHtml(): string { return $this->bodyHtml; }
    public function getImagePath(): ?string { return $this->imagePath; }
    public function isVisible(): bool { return $this->isVisible; }
    public function getPublishedAt(): \DateTimeImmutable { return $this->publishedAt; }
    public function getShowFrom(): ?\DateTimeImmutable { return $this->showFrom; }
    public function getShowUntil(): ?\DateTimeImmutable { return $this->showUntil; }

    public function update(string $title, string $subtitle, string $bodyHtml, ?string $imagePath, bool $isVisible, \DateTimeImmutable $publishedAt, ?\DateTimeImmutable $showFrom, ?\DateTimeImmutable $showUntil): void
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->bodyHtml = $bodyHtml;
        $this->imagePath = $imagePath;
        $this->isVisible = $isVisible;
        $this->publishedAt = $publishedAt;
        $this->showFrom = $showFrom;
        $this->showUntil = $showUntil;
    }
}
