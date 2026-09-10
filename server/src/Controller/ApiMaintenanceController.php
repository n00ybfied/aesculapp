<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiMaintenanceController
{
    public function __construct(private readonly string $cronSecret)
    {
    }

    #[Route('/api/v1/internal/maintenance/purge-unverified-users', name: 'api_v1_maintenance_purge_unverified_users', methods: ['POST'])]
    public function purgeUnverifiedUsers(Request $request, UserRepository $users): JsonResponse
    {
        $providedSecret = $request->headers->get('X-Cron-Secret');
        if ($this->cronSecret === '' || !is_string($providedSecret) || !hash_equals($this->cronSecret, $providedSecret)) {
            return new JsonResponse(null, JsonResponse::HTTP_NOT_FOUND);
        }

        $deletedUsers = $users->deleteInactiveCreatedBefore(new \DateTimeImmutable('-30 days'));

        return new JsonResponse(['deletedUsers' => $deletedUsers]);
    }
}
