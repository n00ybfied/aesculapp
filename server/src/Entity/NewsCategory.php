<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'news_category')]
#[ORM\UniqueConstraint(name: 'uniq_news_category_tenant_name', columns: ['tenant_id', 'name'])]
class NewsCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 100)]
    private string $name;

    public function __construct(Tenant $tenant, string $name)
    {
        $this->tenant = $tenant;
        $this->name = $name;
    }

    public function getId(): ?int { return $this->id; }
    public function getTenant(): Tenant { return $this->tenant; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
}
