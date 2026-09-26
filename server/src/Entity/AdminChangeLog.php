<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'admin_change_log')]
#[ORM\Index(name: 'idx_admin_change_subject', columns: ['tenant_id', 'entity_type', 'entity_id', 'id'])]
class AdminChangeLog
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'tenant_id')]
    private int $tenantId;

    #[ORM\Column(name: 'actor_user_id')]
    private int $actorUserId;

    #[ORM\Column(name: 'actor_name', length: 160)]
    private string $actorName;

    #[ORM\Column(name: 'entity_type', length: 100)]
    private string $entityType;

    #[ORM\Column(name: 'entity_id', length: 100, nullable: true)]
    private ?string $entityId;

    #[ORM\Column(length: 10)]
    private string $action;

    #[ORM\Column(name: 'request_path', length: 255)]
    private string $requestPath;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;
}
