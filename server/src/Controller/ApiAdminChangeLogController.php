<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\AdminAreaPermissions;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAdminChangeLogController
{
    private const AREAS = [
        'Tenant' => 'settings',
        'NotificationTemplate' => 'settings',
        'NewsPost' => 'news',
        'NewsCategory' => 'news',
        'Reward' => 'rewards',
        'Coupon' => 'coupons',
        'Appointment' => 'appointments',
        'AppointmentType' => 'appointments',
        'AppointmentResource' => 'appointments',
        'AppointmentAvailability' => 'appointments',
        'AppointmentBlock' => 'appointments',
        'AppointmentCancellationNotice' => 'appointments',
        'DashboardSlide' => 'slider',
        'MediaAsset' => 'media',
        'ChatMessage' => 'chat',
        'ChatConversation' => 'chat',
        'StaffInvitation' => 'users',
        'TenantMembership' => 'users',
        'User' => 'users',
        'PointTransaction' => 'customers',
        'PointAccount' => 'customers',
        'ActiveRedemption' => 'redemptions',
        'CouponRedemption' => 'redemptions',
    ];

    public function __construct(
        private readonly ActiveTenantProvider $tenants,
        private readonly TenantMembershipRepository $memberships,
        private readonly AdminAreaPermissions $permissions,
        private readonly Security $security,
        private readonly Connection $connection,
    ) {
    }

    #[Route('/api/v1/admin/audit', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $type = $request->query->getString('entityType');
        $requestPath = trim($request->query->getString('requestPath'));
        $area = $type === 'Tenant' && $requestPath === '/api/v1/admin/dashboard/slides/settings'
            ? 'slider' : (self::AREAS[$type] ?? null);
        $user = $this->security->getUser();
        if (!$user instanceof User || ($area === null && $type !== 'all')) return new JsonResponse(['message' => 'Forbidden.'], 403);
        $tenant = $this->tenants->get();
        $membership = $this->memberships->findForUserAndTenant($user, $tenant);
        if ($membership === null || ($type === 'all'
            ? !in_array('ROLE_TENANT_ADMIN', $membership->getRoles(), true)
            : !$this->permissions->can($membership, $area))) return new JsonResponse(['message' => 'Forbidden.'], 403);

        $entityId = trim($request->query->getString('entityId'));
        if (mb_strlen($entityId) > 100) return new JsonResponse(['message' => 'Invalid entity ID.'], 422);
        $sql = 'SELECT id, actor_user_id, actor_name, entity_type, entity_id, action, occurred_at FROM admin_change_log WHERE tenant_id = :tenant';
        $parameters = ['tenant' => $tenant->getId()];
        if ($type !== 'all') {
            $sql .= ' AND entity_type = :type';
            $parameters['type'] = $type;
        }
        if ($entityId !== '') {
            $sql .= ' AND entity_id = :entityId';
            $parameters['entityId'] = $entityId;
        }
        if ($requestPath !== '') {
            if (mb_strlen($requestPath) > 255 || !str_starts_with($requestPath, '/api/v1/admin/')) {
                return new JsonResponse(['message' => 'Invalid request path.'], 422);
            }
            $sql .= ' AND request_path = :requestPath';
            $parameters['requestPath'] = $requestPath;
        }
        $before = $request->query->getInt('before');
        if ($before > 0) {
            $sql .= ' AND id < :before';
            $parameters['before'] = $before;
        }
        $sql .= ' ORDER BY id DESC LIMIT 31';

        $entries = $this->connection->fetchAllAssociative($sql, $parameters);
        $hasMore = count($entries) > 30;
        return new JsonResponse(['hasMore' => $hasMore, 'changes' => array_map(static fn (array $entry): array => [
            'id' => (int) $entry['id'],
            'actorUserId' => (int) $entry['actor_user_id'],
            'actorName' => $entry['actor_name'],
            'entityType' => $entry['entity_type'],
            'entityId' => $entry['entity_id'],
            'action' => $entry['action'],
            'occurredAt' => (new \DateTimeImmutable($entry['occurred_at'], new \DateTimeZone('UTC')))->format(DATE_ATOM),
        ], array_slice($entries, 0, 30))]);
    }
}
