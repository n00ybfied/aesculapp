<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'media_asset')]
class MediaAsset
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner;
    #[ORM\Column(length: 255)] private string $path;
    #[ORM\Column(length: 255)] private string $originalName;
    #[ORM\Column(length: 30)] private string $visibility;
    #[ORM\Column(length: 50)] private string $purpose;
    #[ORM\Column] private int $width;
    #[ORM\Column] private int $height;
    #[ORM\Column] private int $fileSize;
    #[ORM\Column] private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, ?User $owner, string $path, string $originalName, string $visibility, string $purpose, int $width, int $height, int $fileSize)
    { $this->tenant=$tenant; $this->owner=$owner; $this->path=$path; $this->originalName=$originalName; $this->visibility=$visibility; $this->purpose=$purpose; $this->width=$width; $this->height=$height; $this->fileSize=$fileSize; $this->createdAt=new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; } public function getTenant(): Tenant { return $this->tenant; } public function getOwner(): ?User { return $this->owner; } public function getPath(): string { return $this->path; } public function getOriginalName(): string { return $this->originalName; } public function getVisibility(): string { return $this->visibility; } public function getPurpose(): string { return $this->purpose; } public function getWidth(): int { return $this->width; } public function getHeight(): int { return $this->height; } public function getFileSize(): int { return $this->fileSize; } public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
