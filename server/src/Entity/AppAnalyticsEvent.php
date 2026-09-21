<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'app_analytics_event')]
#[ORM\Index(columns: ['tenant_id', 'event_type', 'created_at'], name: 'idx_analytics_tenant_type_date')]
#[ORM\Index(columns: ['tenant_id', 'subject_type', 'subject_id'], name: 'idx_analytics_subject')]
class AppAnalyticsEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 40)]
    private string $eventType;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $subjectType;

    #[ORM\Column(nullable: true)]
    private ?int $subjectId;

    #[ORM\Column(options: ['default' => 0])]
    private int $durationSeconds;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Tenant $tenant, User $user, string $eventType, ?string $subjectType = null, ?int $subjectId = null, int $durationSeconds = 0)
    {
        $this->tenant = $tenant;
        $this->user = $user;
        $this->eventType = $eventType;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->durationSeconds = $durationSeconds;
        $this->createdAt = new \DateTimeImmutable();
    }
}
