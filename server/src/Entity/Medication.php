<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'medication')]
class Medication
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Tenant $tenant;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\Column(type: 'text')]
    public string $encryptedName = '';

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $encryptedDosage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $encryptedSchedule = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $encryptedNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $encryptedImage = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    public ?\DateTimeImmutable $refillDate = null;

    #[ORM\Column]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column]
    public \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, User $user)
    {
        $this->tenant = $tenant;
        $this->user = $user;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }
}
