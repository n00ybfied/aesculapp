<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{TenantMembership, User};
use App\Repository\TenantMembershipRepository;
use App\Service\{ActiveTenantProvider, NativePushService};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class ApiNativePushController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly NativePushService $push,
    ) {
    }

    #[Route('/api/v1/profile/push/native/config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        if ($this->membership() === null) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['configured' => $this->push->configured()]);
    }

    #[Route('/api/v1/profile/push/native', methods: ['POST', 'DELETE'])]
    public function device(Request $request): JsonResponse
    {
        $membership = $this->membership();
        if ($membership === null) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        $data = $request->toArray();
        $token = $data['token'] ?? null;
        if (!is_string($token) || !NativePushService::validToken($token)) {
            return new JsonResponse(['message' => 'Ungültiges Gerätetoken.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $user = $membership->getUser();
        $tenant = $this->tenant->get();
        if ($request->isMethod('DELETE')) {
            $this->push->removeByToken($token);
            return new JsonResponse(['success' => true]);
        }
        if (!$this->push->configured()) {
            return new JsonResponse(['message' => 'Nativer Push ist auf diesem Server noch nicht eingerichtet.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }
        $appId = $data['appId'] ?? null;
        try {
            if (($data['platform'] ?? null) !== 'android' || !is_string($appId) || !$this->push->register($user, $tenant, $token, $appId)) {
                return new JsonResponse(['message' => 'Ungültige App-Registrierung.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } catch (\DomainException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/v1/profile/push/native/status', methods: ['POST'])]
    public function status(Request $request): JsonResponse
    {
        $membership = $this->membership();
        if ($membership === null) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        $token = $request->toArray()['token'] ?? null;
        return new JsonResponse([
            'configured' => $this->push->configured(),
            'registered' => is_string($token) && $this->push->isRegistered($membership->getUser(), $this->tenant->get(), $token),
        ]);
    }

    private function membership(): ?TenantMembership
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }
        $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get());
        return $membership !== null && in_array('ROLE_CUSTOMER', $membership->getRoles(), true) ? $membership : null;
    }
}
