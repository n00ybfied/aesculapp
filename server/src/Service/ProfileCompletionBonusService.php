<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PointAccount;
use App\Entity\PointTransaction;
use App\Entity\TenantMembership;
use Doctrine\ORM\EntityManagerInterface;

final class ProfileCompletionBonusService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PointAccountProvisioner $pointAccounts,
    ) {
    }

    public function isComplete(TenantMembership $membership): bool
    {
        return $membership->isCustomerSetupCompleted() && $this->missingFields($membership) === [];
    }

    /** @return list<string> */
    public function missingFields(TenantMembership $membership): array
    {
        $user = $membership->getUser();
        $fields = [
            'Anrede' => $user->getSalutation() !== null,
            'Vorname' => $this->hasText($user->getFirstName()),
            'Nachname' => $this->hasText($user->getLastName()),
            'Telefon' => $this->hasText($user->getPhone()),
            'Straße und Hausnummer' => $this->hasText($user->getStreetAddress()),
            'Postleitzahl' => $this->hasText($user->getPostalCode()),
            'Ort' => $this->hasText($user->getCity()),
            'Geburtsdatum' => $user->getBirthDate() !== null,
        ];

        return array_keys(array_filter($fields, static fn (bool $complete): bool => !$complete));
    }

    public function awardedPoints(TenantMembership $membership): ?int
    {
        if (!$membership->hasProfileCompletionBonus()) return null;
        $account = $this->entityManager->getRepository(PointAccount::class)->findOneBy([
            'tenant' => $membership->getTenant(),
            'owner' => $membership->getUser(),
        ]);
        if (!$account instanceof PointAccount) return null;
        $transaction = $this->entityManager->getRepository(PointTransaction::class)->findOneBy([
            'account' => $account,
            'type' => 'profile_completion_bonus',
        ]);
        return $transaction instanceof PointTransaction ? $transaction->getPoints() : null;
    }

    /** Must be called inside the same database transaction as the profile update. */
    public function awardIfEligible(TenantMembership $membership): int
    {
        $points = $membership->getTenant()->getProfileCompletionBonusPoints();
        if ($points < 1 || $membership->hasProfileCompletionBonus() || !$this->isComplete($membership)) {
            return 0;
        }

        // The conditional update is the single-claim gate across concurrent profile saves.
        $claimed = $this->entityManager->getConnection()->executeStatement(
            'UPDATE tenant_membership SET profile_completion_bonus_awarded_at = CURRENT_TIMESTAMP WHERE id = :id AND profile_completion_bonus_awarded_at IS NULL',
            ['id' => $membership->getId()],
        );
        if ($claimed !== 1) {
            return 0;
        }

        $membership->markProfileCompletionBonusAwarded();
        $account = $this->pointAccounts->getOrCreate($membership->getTenant(), $membership->getUser());
        $this->entityManager->persist(new PointTransaction($account, $points, 'profile_completion_bonus', 'Bonus für vollständiges Profil'));

        return $points;
    }

    private function hasText(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
