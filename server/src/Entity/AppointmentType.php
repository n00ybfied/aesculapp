<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AppointmentType
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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column]
    private int $durationMinutes;

    #[ORM\Column(options: ['default' => 5])]
    private int $bufferMinutes;

    #[ORM\Column(options: ['default' => true])]
    private bool $isVisible = true;

    public function __construct(Tenant $tenant, string $title, ?string $description, int $duration, int $buffer = 5)
    {
        $this->tenant = $tenant;
        $this->title = $title;
        $this->description = $description;
        $this->durationMinutes = $duration;
        $this->bufferMinutes = $buffer;
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getDurationMinutes(): int { return $this->durationMinutes; }
    public function getBufferMinutes(): int { return $this->bufferMinutes; }
    public function isVisible(): bool { return $this->isVisible; }

    public function update(string $title, ?string $description, int $duration, int $buffer, bool $visible): void
    {
        $this->title = $title;
        $this->description = $description;
        $this->durationMinutes = $duration;
        $this->bufferMinutes = $buffer;
        $this->isVisible = $visible;
    }
}
