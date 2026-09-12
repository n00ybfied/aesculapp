<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenant')]
#[ORM\UniqueConstraint(name: 'uniq_tenant_slug', columns: ['slug'])]
class Tenant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $name;

    #[ORM\Column(length: 100)]
    private string $slug;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $squareLogoPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $faviconPath = null;

    #[ORM\Column(options: ['default' => 1230])]
    private int $initialPoints = 1230;

    #[ORM\Column(options: ['default' => 200])]
    private int $birthdayBonusPoints = 200;

    #[ORM\Column(options: ['default' => 10])]
    private int $pointsPerEuro = 10;
    #[ORM\Column(options: ['default' => false])]
    private bool $allowDuplicateReceiptImports = false;
    #[ORM\Column(options: ['default' => false])]
    private bool $showCustomerDebugOutput = false;
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $receiptQrPrefix = null;
    #[ORM\Column(length: 2048, nullable: true)] private ?string $websiteUrl = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $smtpHost = null;
    #[ORM\Column(nullable: true)] private ?int $smtpPort = null;
    #[ORM\Column(length: 20, nullable: true)] private ?string $smtpEncryption = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $smtpUsername = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $smtpPasswordEncrypted = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $smtpFrom = null;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getLogoPath(): ?string { return $this->logoPath; }
    public function getSquareLogoPath(): ?string { return $this->squareLogoPath; }
    public function getFaviconPath(): ?string { return $this->faviconPath; }
    public function setLogoPath(?string $path): void { $this->logoPath = $path; }
    public function setSquareLogoPath(?string $path): void { $this->squareLogoPath = $path; }
    public function setFaviconPath(?string $path): void { $this->faviconPath = $path; }
    public function getInitialPoints(): int { return $this->initialPoints; }
    public function setInitialPoints(int $points): void { $this->initialPoints = $points; }
    public function getBirthdayBonusPoints(): int { return $this->birthdayBonusPoints; }
    public function setBirthdayBonusPoints(int $points): void { $this->birthdayBonusPoints = $points; }
    public function getPointsPerEuro(): int { return $this->pointsPerEuro; }
    public function setPointsPerEuro(int $points): void { $this->pointsPerEuro = $points; }
    public function allowsDuplicateReceiptImports(): bool { return $this->allowDuplicateReceiptImports; }
    public function setAllowDuplicateReceiptImports(bool $value): void { $this->allowDuplicateReceiptImports = $value; }
    public function showsCustomerDebugOutput(): bool { return $this->showCustomerDebugOutput; }
    public function setShowCustomerDebugOutput(bool $value): void { $this->showCustomerDebugOutput = $value; }
    public function getReceiptQrPrefix(): ?string { return $this->receiptQrPrefix; }
    public function setReceiptQrPrefix(?string $value): void { $this->receiptQrPrefix = $value; }
    public function getWebsiteUrl(): ?string { return $this->websiteUrl; } public function setWebsiteUrl(?string $value): void { $this->websiteUrl = $value; }
    public function getSmtpHost(): ?string { return $this->smtpHost; } public function setSmtpHost(?string $value): void { $this->smtpHost = $value; }
    public function getSmtpPort(): ?int { return $this->smtpPort; } public function setSmtpPort(?int $value): void { $this->smtpPort = $value; }
    public function getSmtpEncryption(): ?string { return $this->smtpEncryption; } public function setSmtpEncryption(?string $value): void { $this->smtpEncryption = $value; }
    public function getSmtpUsername(): ?string { return $this->smtpUsername; } public function setSmtpUsername(?string $value): void { $this->smtpUsername = $value; }
    public function getSmtpPasswordEncrypted(): ?string { return $this->smtpPasswordEncrypted; } public function setSmtpPasswordEncrypted(?string $value): void { $this->smtpPasswordEncrypted = $value; }
    public function getSmtpFrom(): ?string { return $this->smtpFrom; } public function setSmtpFrom(?string $value): void { $this->smtpFrom = $value; }
}
