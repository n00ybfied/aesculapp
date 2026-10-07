<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewsPushConsentEvent;
use App\Entity\TenantMembership;
use Doctrine\ORM\EntityManagerInterface;

final class NewsPushConsentRecorder
{
    public const TEXT_VERSION = 'news-push-v1';
    public const CONSENT_TEXT = 'Ich möchte Push-Mitteilungen über neue Apotheken-News, Aktionen und Angebote zu meinen ausgewählten Nachrichtenkategorien erhalten. Ich kann diese Einstellung jederzeit im Profil deaktivieren.';

    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function update(TenantMembership $membership, bool $enabled, string $source): void
    {
        if ($membership->isNewsPushEnabled() === $enabled) return;

        $membership->setNewsPushEnabled($enabled);
        $this->entityManager->persist(new NewsPushConsentEvent(
            $membership,
            $enabled,
            self::TEXT_VERSION,
            self::CONSENT_TEXT,
            $source,
        ));
    }
}
