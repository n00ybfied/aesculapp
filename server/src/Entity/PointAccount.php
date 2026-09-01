<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity]
#[ORM\Table(name: 'point_account')]
#[ORM\UniqueConstraint(name: 'uniq_point_account_owner', columns: ['tenant_id', 'owner_id'])]
class PointAccount {
 #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
 #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Tenant $tenant;
 #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private User $owner;
 public function __construct(Tenant $tenant, User $owner) {$this->tenant=$tenant;$this->owner=$owner;}
 public function getId(): ?int{return $this->id;} public function getOwner(): User{return $this->owner;}
}
