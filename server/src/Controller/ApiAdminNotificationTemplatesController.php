<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\AdminAreaPermissions;
use App\Service\NotificationTemplates;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/settings/notification-templates')]
final class ApiAdminNotificationTemplatesController
{
    public function __construct(
        private readonly ActiveTenantProvider $tenantProvider,
        private readonly TenantMembershipRepository $memberships,
        private readonly AdminAreaPermissions $permissions,
        private readonly NotificationTemplates $templates,
        private readonly Security $security,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        if (!$this->allowed()) return new JsonResponse(['message' => 'Forbidden.'], 403);
        return new JsonResponse(['templates' => $this->templates->listFor($this->tenantProvider->get())]);
    }

    #[Route('/{key}', methods: ['PUT'])]
    public function save(string $key, Request $request): JsonResponse
    {
        if (!$this->allowed()) return new JsonResponse(['message' => 'Forbidden.'], 403);
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !is_string($data['title'] ?? null) || !is_string($data['body'] ?? null)) {
                throw new \InvalidArgumentException('Bitte Titel und Text angeben.');
            }
            $this->templates->save($this->tenantProvider->get(), $key, $data['title'], $data['body']);
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }
        return $this->list();
    }

    #[Route('/{key}', methods: ['DELETE'])]
    public function reset(string $key): JsonResponse
    {
        if (!$this->allowed()) return new JsonResponse(['message' => 'Forbidden.'], 403);
        try {
            $this->templates->reset($this->tenantProvider->get(), $key);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        }
        return $this->list();
    }

    private function allowed(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return false;
        $membership = $this->memberships->findForUserAndTenant($user, $this->tenantProvider->get());
        return $membership !== null && $this->permissions->can($membership, 'settings');
    }
}
