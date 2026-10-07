<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'news_push_consent_event')]
#[ORM\Index(name: 'idx_news_push_consent_membership', columns: ['membership_id', 'occurred_at'])]
class NewsPushConsentEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'membership_id', nullable: false, onDelete: 'CASCADE')]
    private TenantMembership $membership;

    #[ORM\Column(length: 12)]
    private string $action;

    #[ORM\Column(length: 32)]
    private string $textVersion;

    #[ORM\Column(type: 'text')]
    private string $consentText;

    #[ORM\Column(length: 16)]
    private string $source;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct(TenantMembership $membership, bool $enabled, string $textVersion, string $consentText, string $source)
    {
        $this->membership = $membership;
        $this->action = $enabled ? 'granted' : 'withdrawn';
        $this->textVersion = $textVersion;
        $this->consentText = $consentText;
        $this->source = $source;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getAction(): string { return $this->action; }
    public function getTextVersion(): string { return $this->textVersion; }
    public function getConsentText(): string { return $this->consentText; }
    public function getSource(): string { return $this->source; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
