<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'family_connection')]
#[ORM\UniqueConstraint(name: 'uniq_family_connection_pair', columns: ['tenant_id', 'participant_one_id', 'participant_two_id'])]
class FamilyConnection
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;
    #[ORM\ManyToOne, ORM\JoinColumn(name: 'participant_one_id', nullable: false, onDelete: 'CASCADE')]
    private User $participantOne;
    #[ORM\ManyToOne, ORM\JoinColumn(name: 'participant_two_id', nullable: false, onDelete: 'CASCADE')]
    private User $participantTwo;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $invitedBy;
    #[ORM\Column(length: 64)]
    private string $tokenHash;
    #[ORM\Column(length: 16)]
    private string $status = 'pending';
    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;
    #[ORM\Column(options: ['default' => false])]
    private bool $oneAllowsTwoMedicationView = false;
    #[ORM\Column(options: ['default' => false])]
    private bool $oneAllowsTwoMedicationManage = false;
    #[ORM\Column(options: ['default' => false])]
    private bool $twoAllowsOneMedicationView = false;
    #[ORM\Column(options: ['default' => false])]
    private bool $twoAllowsOneMedicationManage = false;
    #[ORM\Column(length: 16)]
    private string $pointSharingStatus = 'none';
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $pointSharingRequestedBy = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pointSharingAcceptedAt = null;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, User $invitedBy, User $recipient, string $tokenHash)
    {
        if ($invitedBy->getId() === $recipient->getId()) throw new \InvalidArgumentException('A user cannot invite themselves.');
        $this->tenant = $tenant;
        [$this->participantOne, $this->participantTwo] = ($invitedBy->getId() < $recipient->getId()) ? [$invitedBy, $recipient] : [$recipient, $invitedBy];
        $this->invitedBy = $invitedBy;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = new \DateTimeImmutable('+7 days');
        $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getInvitedBy(): User { return $this->invitedBy; }
    public function getParticipantOne(): User { return $this->participantOne; }
    public function getParticipantTwo(): User { return $this->participantTwo; }
    public function isParticipant(User $user): bool { return $user->getId() === $this->participantOne->getId() || $user->getId() === $this->participantTwo->getId(); }
    public function other(User $user): User { if (!$this->isParticipant($user)) throw new \LogicException('Not a participant.'); return $user->getId() === $this->participantOne->getId() ? $this->participantTwo : $this->participantOne; }
    public function isInvited(User $user): bool { return $user->getId() === $this->other($this->invitedBy)->getId(); }
    public function accepts(User $user, string $hash): bool { return $this->status === 'pending' && $this->expiresAt >= new \DateTimeImmutable() && $this->isInvited($user) && hash_equals($this->tokenHash, $hash); }
    public function canBeAcceptedBy(User $user): bool { return $this->status === 'pending' && $this->expiresAt >= new \DateTimeImmutable() && $this->isInvited($user); }
    public function getPointSharingStatus(): string { return $this->pointSharingStatus; }
    public function isPointSharingRequestedBy(User $user): bool { return $this->pointSharingRequestedBy?->getId() === $user->getId(); }
    public function requestPointSharing(User $user): void { if (!$this->isAccepted() || !$this->isParticipant($user) || $this->pointSharingStatus !== 'none') throw new \LogicException('Point sharing cannot be requested.'); $this->pointSharingStatus = 'pending'; $this->pointSharingRequestedBy = $user; }
    public function canAcceptPointSharing(User $user): bool { return $this->pointSharingStatus === 'pending' && $this->isParticipant($user) && !$this->isPointSharingRequestedBy($user); }
    public function acceptPointSharing(User $user): void { if (!$this->canAcceptPointSharing($user)) throw new \LogicException('Point sharing cannot be accepted.'); $this->pointSharingStatus = 'accepted'; $this->pointSharingAcceptedAt = new \DateTimeImmutable(); }
    public function accept(): void { $this->status = 'accepted'; $this->acceptedAt = new \DateTimeImmutable(); }
    public function isAccepted(): bool { return $this->status === 'accepted'; }
    public function canViewMedication(User $owner, User $grantee): bool { return $this->isAccepted() && $this->isParticipant($owner) && $this->isParticipant($grantee) && $owner->getId() !== $grantee->getId() && ($owner->getId() === $this->participantOne->getId() ? $this->oneAllowsTwoMedicationView : $this->twoAllowsOneMedicationView); }
    public function canManageMedication(User $owner, User $grantee): bool { return $this->canViewMedication($owner, $grantee) && ($owner->getId() === $this->participantOne->getId() ? $this->oneAllowsTwoMedicationManage : $this->twoAllowsOneMedicationManage); }
    public function setMedicationAccess(User $owner, bool $view, bool $manage): void { if (!$this->isAccepted() || !$this->isParticipant($owner)) throw new \LogicException('Cannot change access.'); $manage = $view && $manage; if ($owner->getId() === $this->participantOne->getId()) { $this->oneAllowsTwoMedicationView = $view; $this->oneAllowsTwoMedicationManage = $manage; } else { $this->twoAllowsOneMedicationView = $view; $this->twoAllowsOneMedicationManage = $manage; } }
}
