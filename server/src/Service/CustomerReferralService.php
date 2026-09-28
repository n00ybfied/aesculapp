<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CustomerReferral;
use App\Entity\PointTransaction;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class CustomerReferralService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PointAccountProvisioner $pointAccounts,
    ) {
    }

    public function codeFor(TenantMembership $membership): string
    {
        if ($membership->getReferralCode() !== null) {
            return $membership->getReferralCode();
        }

        $code = bin2hex(random_bytes(16));
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE tenant_membership SET referral_code = ? WHERE id = ? AND referral_code IS NULL',
            [$code, $membership->getId()],
        );
        $this->entityManager->refresh($membership);

        return $membership->getReferralCode() ?? throw new \LogicException('Referral code could not be created.');
    }

    public function findInviter(Tenant $tenant, string $code): ?TenantMembership
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $code)) {
            return null;
        }

        $membership = $this->entityManager->getRepository(TenantMembership::class)->findOneBy([
            'tenant' => $tenant,
            'referralCode' => $code,
        ]);

        return $membership instanceof TenantMembership && in_array('ROLE_CUSTOMER', $membership->getRoles(), true)
            ? $membership
            : null;
    }

    public function recordInvitation(Tenant $tenant, TenantMembership $inviter, User $invitee): void
    {
        $this->entityManager->persist(new CustomerReferral($tenant, $inviter, $invitee));
    }

    /** @return array{inviter: User, points: int, referralId: int}|null */
    public function awardVerifiedInvitation(Tenant $tenant, User $invitee): ?array
    {
        $referral = $this->entityManager->getRepository(CustomerReferral::class)->findOneBy([
            'tenant' => $tenant,
            'invitee' => $invitee,
        ]);
        if (!$referral instanceof CustomerReferral || $referral->getAwardedAt() !== null) {
            return null;
        }

        $inviter = $referral->getInviter();
        $points = in_array('ROLE_CUSTOMER', $inviter->getRoles(), true) ? $tenant->getReferralBonusPoints() : 0;
        $claimed = $this->entityManager->getConnection()->executeStatement(
            'UPDATE customer_referral SET awarded_at = NOW(), awarded_points = ? WHERE id = ? AND awarded_at IS NULL',
            [$points, $referral->getId()],
        );
        if ($claimed === 1 && $points > 0) {
            $account = $this->pointAccounts->getOrCreate($tenant, $inviter->getUser());
            $this->entityManager->persist(new PointTransaction($account, $points, 'referral_bonus', 'Freund eingeladen'));
        }

        return $claimed === 1 ? [
            'inviter' => $inviter->getUser(),
            'points' => $points,
            'referralId' => $referral->getId(),
        ] : null;
    }

    public function successfulCount(TenantMembership $membership): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM customer_referral WHERE inviter_id = ? AND awarded_at IS NOT NULL',
            [$membership->getId()],
        );
    }
}
