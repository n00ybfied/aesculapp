<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AppointmentBlock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?AppointmentType $type;

    #[ORM\Column(length: 10)]
    private string $startsOn;

    #[ORM\Column(length: 10)]
    private string $endsOn;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $startsAt;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $endsAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment;

    public function __construct(Tenant $tenant, ?AppointmentType $type, string $startsOn, string $endsOn, ?string $startsAt, ?string $endsAt, ?string $comment)
    {
        $this->tenant = $tenant;
        $this->update($type, $startsOn, $endsOn, $startsAt, $endsAt, $comment);
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getType(): ?AppointmentType { return $this->type; }
    public function getStartsOn(): string { return $this->startsOn; }
    public function getEndsOn(): string { return $this->endsOn; }
    public function getStartsAt(): ?string { return $this->startsAt; }
    public function getEndsAt(): ?string { return $this->endsAt; }
    public function getComment(): ?string { return $this->comment; }

    public function update(?AppointmentType $type, string $startsOn, string $endsOn, ?string $startsAt, ?string $endsAt, ?string $comment): void
    {
        $this->type = $type;
        $this->startsOn = $startsOn;
        $this->endsOn = $endsOn;
        $this->startsAt = $startsAt;
        $this->endsAt = $endsAt;
        $this->comment = $comment;
    }
}
