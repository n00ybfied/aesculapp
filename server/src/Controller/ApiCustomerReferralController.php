<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\CustomerReferralService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiCustomerReferralController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly CustomerReferralService $referrals,
        private readonly string $clientUrl,
    ) {
    }

    #[Route('/api/v1/referrals', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        $user = $this->security->getUser();
        $tenant = $this->activeTenant->get();
        $membership = $user instanceof User ? $this->memberships->findForUserAndTenant($user, $tenant) : null;
        if ($membership === null || !in_array('ROLE_CUSTOMER', $membership->getRoles(), true)) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $code = $this->referrals->codeFor($membership);
        return new JsonResponse([
            'inviteUrl' => rtrim($this->clientUrl, '/') . '/registrierung?ref=' . $code,
            'bonusPoints' => $tenant->getReferralBonusPoints(),
            'successfulInvitations' => $this->referrals->successfulCount($membership),
        ]);
    }
}
