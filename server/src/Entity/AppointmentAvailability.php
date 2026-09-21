<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AppointmentAvailability
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AppointmentResource $resource;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AppointmentType $type;

    #[ORM\Column]
    private int $weekday;

    #[ORM\Column(length: 5)]
    private string $startsAt;

    #[ORM\Column(length: 5)]
    private string $endsAt;

    public function __construct(AppointmentResource $resource, AppointmentType $type, int $weekday, string $startsAt, string $endsAt)
    {
        $this->resource = $resource;
        $this->type = $type;
        $this->weekday = $weekday;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getResource(): AppointmentResource { return $this->resource; }
    public function getType(): AppointmentType { return $this->type; }
    public function getWeekday(): int { return $this->weekday; }
    public function getStartsAt(): string { return $this->startsAt; }
    public function getEndsAt(): string { return $this->endsAt; }

    public function update(AppointmentResource $resource, AppointmentType $type, int $weekday, string $startsAt, string $endsAt): void
    {
        $this->resource = $resource;
        $this->type = $type;
        $this->weekday = $weekday;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
    }
}
