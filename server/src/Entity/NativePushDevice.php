<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'native_push_device')]
#[ORM\UniqueConstraint(name: 'uniq_native_push_token', columns: ['token_hash'])]
class NativePushDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public User $user;

    #[ORM\Column(length: 64)]
    public string $tokenHash;

    #[ORM\Column(type: 'text')]
    public string $encryptedToken;

    #[ORM\Column(length: 16)]
    public string $platform;

    #[ORM\Column(length: 120)]
    public string $appId;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, User $user, string $tokenHash, string $encryptedToken, string $platform, string $appId)
    {
        $this->tenant = $tenant;
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->encryptedToken = $encryptedToken;
        $this->platform = $platform;
        $this->appId = $appId;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
