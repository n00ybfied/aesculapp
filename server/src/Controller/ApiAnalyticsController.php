<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppAnalyticsEvent;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAnalyticsController
{
    private const ITEM_TYPES = ['news', 'reward', 'coupon'];
    private const ITEM_EVENTS = ['view_item', 'select_item', 'redeem_item'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
    ) {
    }

    #[Route('/api/v1/analytics/events', methods: ['POST'])]
    public function record(Request $request): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->memberships->hasCustomerMembershipFor($user, $this->tenant->get())) {
            return new JsonResponse(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Invalid analytics event.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $eventName = $payload['eventName'] ?? null;
        $itemType = $payload['itemType'] ?? null;
        $itemId = $payload['itemId'] ?? null;
        $engagementTimeMsec = $payload['engagementTimeMsec'] ?? 0;
        $isItemEvent = is_string($eventName)
            && in_array($eventName, self::ITEM_EVENTS, true)
            && is_string($itemType)
            && in_array($itemType, self::ITEM_TYPES, true)
            && is_int($itemId)
            && $itemId > 0;
        $isCheckout = $eventName === 'begin_checkout';
        $isEngagement = $eventName === 'user_engagement'
            && is_int($engagementTimeMsec)
            && $engagementTimeMsec >= 1_000
            && $engagementTimeMsec <= 300_000;
        if (!$isItemEvent && !$isCheckout && !$isEngagement) {
            return new JsonResponse(['message' => 'Invalid analytics event.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist(new AppAnalyticsEvent(
            $this->tenant->get(),
            $user,
            $eventName,
            $isItemEvent ? $itemType : null,
            $isItemEvent ? $itemId : null,
            $isEngagement ? (int) floor($engagementTimeMsec / 1_000) : 0,
        ));
        $this->entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
