<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Appointment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private AppointmentResource $resource;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedUser = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private AppointmentType $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $customer;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $guestName;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private \DateTimeImmutable $endsAt;

    #[ORM\Column(length: 32)]
    private string $status = 'reserved';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $customerNote;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ChatConversation $chatConversation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderEmailSentAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderPushSentAt = null;

    public function __construct(
        Tenant $tenant,
        AppointmentResource $resource,
        AppointmentType $type,
        ?User $customer,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?string $note,
        ?string $guestName = null,
    ) {
        if (($customer === null) === ($guestName === null || trim($guestName) === '')) {
            throw new \InvalidArgumentException('Ein Termin benötigt entweder ein Kundenkonto oder einen Gastnamen.');
        }

        $this->tenant = $tenant;
        $this->resource = $resource;
        $this->assignedUser = $resource->getAssignedUser();
        $this->type = $type;
        $this->customer = $customer;
        $this->guestName = $guestName === null ? null : trim($guestName);
        $this->startsAt = $start;
        $this->endsAt = $end;
        $this->customerNote = $note;
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getResource(): AppointmentResource { return $this->resource; }
    public function getAssignedUser(): ?User { return $this->assignedUser; }
    public function getType(): AppointmentType { return $this->type; }
    public function getCustomer(): ?User { return $this->customer; }
    public function getDisplayName(): string { return $this->customer?->getDisplayName() ?? $this->guestName ?? ''; }
    public function getGuestName(): ?string { return $this->guestName; }
    public function getStartsAt(): \DateTimeImmutable { return $this->startsAt; }
    public function getEndsAt(): \DateTimeImmutable { return $this->endsAt; }
    public function getStatus(): string { return $this->status; }
    public function getCustomerNote(): ?string { return $this->customerNote; }
    public function getChatConversation(): ?ChatConversation { return $this->chatConversation; }
    public function setChatConversation(?ChatConversation $conversation): void { $this->chatConversation = $conversation; }
    public function isReminderEmailSent(): bool { return $this->reminderEmailSentAt !== null; }
    public function isReminderPushSent(): bool { return $this->reminderPushSentAt !== null; }
    public function markReminderEmailSent(): void { $this->reminderEmailSentAt = new \DateTimeImmutable(); }
    public function markReminderPushSent(): void { $this->reminderPushSentAt = new \DateTimeImmutable(); }
    public function cancel(): void { $this->status = 'cancelled'; }
    public function awaitStaffConfirmation(): void { $this->status = 'pending_staff_confirmation'; }
    public function confirmByStaff(): void
    {
        if ($this->status !== 'pending_staff_confirmation') {
            throw new \LogicException('Nur ausstehende Termine können bestätigt werden.');
        }
        $this->status = 'reserved';
    }
    public function occupiesSlot(): bool { return in_array($this->status, ['reserved', 'pending_staff_confirmation'], true); }
}
