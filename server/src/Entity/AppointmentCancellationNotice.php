<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AppointmentCancellationNotice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Appointment $appointment;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
        $this->tenant = $appointment->getTenant();
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAppointment(): Appointment { return $this->appointment; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
