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

    #[ORM\Column(options: ['default' => 0])]
    private int $profileCompletionBonusPoints = 0;

    #[ORM\Column(options: ['default' => 200])]
    private int $birthdayBonusPoints = 200;

    #[ORM\Column(options: ['default' => 10])]
    private int $pointsPerEuro = 10;
    #[ORM\Column(options: ['default' => 28])]
    private int $appointmentBookingFutureDays = 28;
    #[ORM\Column(options: ['default' => 24])]
    private int $appointmentCancellationHours = 24;
    #[ORM\Column(options: ['default' => false])]
    private bool $appointmentStaffConfirmationEnabled = false;
    #[ORM\Column(options: ['default' => false])]
    private bool $showAppointmentStaffNames = false;
    #[ORM\Column(options: ['default' => false])]
    private bool $allowDuplicateReceiptImports = false;
    #[ORM\Column(options: ['default' => false])]
    private bool $showCustomerDebugOutput = false;
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $receiptQrPrefix = null;
    #[ORM\Column(length: 2048, nullable: true)] private ?string $websiteUrl = null;
    #[ORM\Column(options: ['default' => false])] private bool $familyPointSharingEnabled = false;
    #[ORM\Column(options: ['default' => false])] private bool $familyPointSharingLocked = false;
    #[ORM\Column(length: 255, nullable: true)] private ?string $smtpHost = null;
    #[ORM\Column(nullable: true)] private ?int $smtpPort = null;
    #[ORM\Column(length: 20, nullable: true)] private ?string $smtpEncryption = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $smtpUsername = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $smtpPasswordEncrypted = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $smtpFrom = null;
    #[ORM\Column(options: ['default' => false])] private bool $appNoticeEnabled = false;
    #[ORM\Column(length: 160, nullable: true)] private ?string $appNoticeTitle = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $appNoticeHtml = null;
    #[ORM\Column(options: ['default' => false])] private bool $birthdayGreetingEnabled = false;
    #[ORM\Column(length: 160, nullable: true)] private ?string $birthdayGreetingTitle = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $birthdayGreetingText = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $birthdayGreetingImagePath = null;
    #[ORM\Column(length: 10, options: ['default' => 'slide'])] private string $dashboardSliderTransition = 'slide';
    #[ORM\Column(options: ['default' => 400])] private int $dashboardSliderAnimationDurationMs = 400;
    #[ORM\Column(options: ['default' => 6000])] private int $dashboardSliderDelayMs = 6000;
    #[ORM\Column(options: ['default' => true])] private bool $dashboardSliderAutoplay = true;
    #[ORM\Column(length: 255, nullable: true)] private ?string $contactAddress = null;
    #[ORM\Column(length: 80, nullable: true)] private ?string $contactPhone = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $contactEmail = null;
    #[ORM\Column(type: 'json', nullable: true)] private ?array $contactOpeningHours = null;
    #[ORM\Column(nullable: true)] private ?float $contactLatitude = null;
    #[ORM\Column(nullable: true)] private ?float $contactLongitude = null;
    #[ORM\Column(nullable: true)] private ?int $contactMapZoom = null;
    #[ORM\Column(length: 2048, nullable: true)] private ?string $contactGoogleMapsUrl = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $contactAdditionalHtml = null;

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
    public function getProfileCompletionBonusPoints(): int { return $this->profileCompletionBonusPoints; }
    public function setProfileCompletionBonusPoints(int $points): void { $this->profileCompletionBonusPoints = $points; }
    public function getBirthdayBonusPoints(): int { return $this->birthdayBonusPoints; }
    public function setBirthdayBonusPoints(int $points): void { $this->birthdayBonusPoints = $points; }
    public function getPointsPerEuro(): int { return $this->pointsPerEuro; }
    public function setPointsPerEuro(int $points): void { $this->pointsPerEuro = $points; }
    public function getAppointmentBookingFutureDays(): int { return $this->appointmentBookingFutureDays; }
    public function setAppointmentBookingFutureDays(int $days): void { $this->appointmentBookingFutureDays = $days; }
    public function getAppointmentCancellationHours(): int { return $this->appointmentCancellationHours; }
    public function setAppointmentCancellationHours(int $hours): void { $this->appointmentCancellationHours = $hours; }
    public function isAppointmentStaffConfirmationEnabled(): bool { return $this->appointmentStaffConfirmationEnabled; }
    public function setAppointmentStaffConfirmationEnabled(bool $enabled): void { $this->appointmentStaffConfirmationEnabled = $enabled; }
    public function showsAppointmentStaffNames(): bool { return $this->showAppointmentStaffNames; }
    public function setShowAppointmentStaffNames(bool $enabled): void { $this->showAppointmentStaffNames = $enabled; }
    public function allowsDuplicateReceiptImports(): bool { return $this->allowDuplicateReceiptImports; }
    public function setAllowDuplicateReceiptImports(bool $value): void { $this->allowDuplicateReceiptImports = $value; }
    public function showsCustomerDebugOutput(): bool { return $this->showCustomerDebugOutput; }
    public function setShowCustomerDebugOutput(bool $value): void { $this->showCustomerDebugOutput = $value; }
    public function getReceiptQrPrefix(): ?string { return $this->receiptQrPrefix; }
    public function setReceiptQrPrefix(?string $value): void { $this->receiptQrPrefix = $value; }
    public function getWebsiteUrl(): ?string { return $this->websiteUrl; } public function setWebsiteUrl(?string $value): void { $this->websiteUrl = $value; }
    public function isFamilyPointSharingEnabled(): bool { return $this->familyPointSharingEnabled; }
    public function setFamilyPointSharingEnabled(bool $value): void { if (!$value && $this->familyPointSharingLocked) throw new \LogicException('Family point sharing cannot be disabled after a pool exists.'); $this->familyPointSharingEnabled = $value; }
    public function isFamilyPointSharingLocked(): bool { return $this->familyPointSharingLocked; }
    public function lockFamilyPointSharing(): void { $this->familyPointSharingLocked = true; $this->familyPointSharingEnabled = true; }
    public function getSmtpHost(): ?string { return $this->smtpHost; } public function setSmtpHost(?string $value): void { $this->smtpHost = $value; }
    public function getSmtpPort(): ?int { return $this->smtpPort; } public function setSmtpPort(?int $value): void { $this->smtpPort = $value; }
    public function getSmtpEncryption(): ?string { return $this->smtpEncryption; } public function setSmtpEncryption(?string $value): void { $this->smtpEncryption = $value; }
    public function getSmtpUsername(): ?string { return $this->smtpUsername; } public function setSmtpUsername(?string $value): void { $this->smtpUsername = $value; }
    public function getSmtpPasswordEncrypted(): ?string { return $this->smtpPasswordEncrypted; } public function setSmtpPasswordEncrypted(?string $value): void { $this->smtpPasswordEncrypted = $value; }
    public function getSmtpFrom(): ?string { return $this->smtpFrom; } public function setSmtpFrom(?string $value): void { $this->smtpFrom = $value; }
    public function isAppNoticeEnabled(): bool { return $this->appNoticeEnabled; } public function setAppNoticeEnabled(bool $value): void { $this->appNoticeEnabled = $value; }
    public function getAppNoticeTitle(): ?string { return $this->appNoticeTitle; } public function setAppNoticeTitle(?string $value): void { $this->appNoticeTitle = $value; }
    public function getAppNoticeHtml(): ?string { return $this->appNoticeHtml; } public function setAppNoticeHtml(?string $value): void { $this->appNoticeHtml = $value; }
    public function isBirthdayGreetingEnabled(): bool { return $this->birthdayGreetingEnabled; } public function setBirthdayGreetingEnabled(bool $value): void { $this->birthdayGreetingEnabled = $value; }
    public function getBirthdayGreetingTitle(): ?string { return $this->birthdayGreetingTitle; } public function setBirthdayGreetingTitle(?string $value): void { $this->birthdayGreetingTitle = $value; }
    public function getBirthdayGreetingText(): ?string { return $this->birthdayGreetingText; } public function setBirthdayGreetingText(?string $value): void { $this->birthdayGreetingText = $value; }
    public function getBirthdayGreetingImagePath(): ?string { return $this->birthdayGreetingImagePath; } public function setBirthdayGreetingImagePath(?string $value): void { $this->birthdayGreetingImagePath = $value; }
    public function getDashboardSliderTransition(): string { return $this->dashboardSliderTransition; }
    public function setDashboardSliderTransition(string $value): void { $this->dashboardSliderTransition = $value; }
    public function getDashboardSliderAnimationDurationMs(): int { return $this->dashboardSliderAnimationDurationMs; }
    public function setDashboardSliderAnimationDurationMs(int $value): void { $this->dashboardSliderAnimationDurationMs = $value; }
    public function getDashboardSliderDelayMs(): int { return $this->dashboardSliderDelayMs; }
    public function setDashboardSliderDelayMs(int $value): void { $this->dashboardSliderDelayMs = $value; }
    public function isDashboardSliderAutoplay(): bool { return $this->dashboardSliderAutoplay; }
    public function setDashboardSliderAutoplay(bool $value): void { $this->dashboardSliderAutoplay = $value; }
    public function getContactAddress(): ?string { return $this->contactAddress; } public function setContactAddress(?string $value): void { $this->contactAddress = $value; }
    public function getContactPhone(): ?string { return $this->contactPhone; } public function setContactPhone(?string $value): void { $this->contactPhone = $value; }
    public function getContactEmail(): ?string { return $this->contactEmail; } public function setContactEmail(?string $value): void { $this->contactEmail = $value; }
    /** @return array<string, string>|null */
    public function getContactOpeningHours(): ?array { return $this->contactOpeningHours; }
    /** @param array<string, string>|null $value */
    public function setContactOpeningHours(?array $value): void { $this->contactOpeningHours = $value; }
    public function getContactLatitude(): ?float { return $this->contactLatitude; } public function setContactLatitude(?float $value): void { $this->contactLatitude = $value; }
    public function getContactLongitude(): ?float { return $this->contactLongitude; } public function setContactLongitude(?float $value): void { $this->contactLongitude = $value; }
    public function getContactMapZoom(): ?int { return $this->contactMapZoom; } public function setContactMapZoom(?int $value): void { $this->contactMapZoom = $value; }
    public function getContactGoogleMapsUrl(): ?string { return $this->contactGoogleMapsUrl; } public function setContactGoogleMapsUrl(?string $value): void { $this->contactGoogleMapsUrl = $value; }
    public function getContactAdditionalHtml(): ?string { return $this->contactAdditionalHtml; } public function setContactAdditionalHtml(?string $value): void { $this->contactAdditionalHtml = $value; }
}
