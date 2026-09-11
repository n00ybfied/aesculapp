<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chat_conversation')]
class ChatConversation {
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] public Tenant $tenant;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] public User $customer;
    #[ORM\Column(length: 20)] public string $status = 'open';
    #[ORM\Column] public \DateTimeImmutable $createdAt;
    #[ORM\Column] public \DateTimeImmutable $updatedAt;
    #[ORM\Column(nullable: true)] public ?\DateTimeImmutable $closedAt = null;
    #[ORM\Column(length: 40)] public string $consentVersion;
    #[ORM\Column] public \DateTimeImmutable $consentedAt;
    public function __construct(Tenant $tenant, User $customer, string $version) {
        $this->tenant = $tenant; $this->customer = $customer;
        $this->createdAt = $this->updatedAt = $this->consentedAt = new \DateTimeImmutable();
        $this->consentVersion = $version;
    }
}
