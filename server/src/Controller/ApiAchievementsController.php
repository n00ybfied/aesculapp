<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\ProfileCompletionBonusService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAchievementsController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly ProfileCompletionBonusService $profileBonus,
    ) {
    }

    #[Route('/api/v1/achievements', name: 'api_v1_achievements', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        if ($membership === null || !in_array('ROLE_CUSTOMER', $membership->getRoles(), true)) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $completed = $membership->hasProfileCompletionBonus() || $this->profileBonus->isComplete($membership);
        $missing = $completed ? [] : $this->profileBonus->missingFields($membership);
        $points = $this->profileBonus->awardedPoints($membership) ?? $membership->getTenant()->getProfileCompletionBonusPoints();

        return new JsonResponse(['achievements' => [[
            'id' => 'complete_profile',
            'title' => 'Profil vervollständigen',
            'description' => 'Ergänzen Sie Ihre persönlichen Angaben in Ihrem Profil.',
            'points' => $points,
            'completed' => $completed,
            'progress' => $completed ? 8 : 8 - count($missing),
            'target' => 8,
            'missingFields' => $missing,
            'actionPath' => '/profil',
        ]]]);
    }
}
