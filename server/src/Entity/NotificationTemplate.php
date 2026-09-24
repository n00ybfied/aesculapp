<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification_template')]
#[ORM\UniqueConstraint(name: 'uniq_notification_template_tenant_key', columns: ['tenant_id', 'template_key'])]
class NotificationTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Tenant $tenant;

    #[ORM\Column(name: 'template_key', length: 100)]
    public string $key;

    #[ORM\Column(length: 200)]
    public string $title;

    #[ORM\Column(type: 'text')]
    public string $body;

    public function __construct(Tenant $tenant, string $key, string $title, string $body)
    {
        $this->tenant = $tenant;
        $this->key = $key;
        $this->title = $title;
        $this->body = $body;
    }
}
