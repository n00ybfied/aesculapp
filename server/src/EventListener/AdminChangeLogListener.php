<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\AdminChangeLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postRemove)]
final class AdminChangeLogListener
{
    private const AUDITED_TYPES = [
        'Tenant', 'NotificationTemplate', 'NewsPost', 'NewsCategory', 'Reward', 'Coupon',
        'Appointment', 'AppointmentType', 'AppointmentResource', 'AppointmentAvailability',
        'AppointmentBlock', 'AppointmentCancellationNotice', 'DashboardSlide', 'MediaAsset',
        'ChatMessage', 'ChatConversation', 'StaffInvitation', 'TenantMembership', 'User',
        'PointTransaction', 'PointAccount', 'ActiveRedemption', 'CouponRedemption',
    ];
    /** @var \WeakMap<object, string> */
    private \WeakMap $removedIds;

    public function __construct(
        private readonly RequestStack $requests,
        private readonly Security $security,
        private readonly Connection $connection,
        private readonly string $tenantSlug,
    ) {
        $this->removedIds = new \WeakMap();
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $identifier = $args->getObjectManager()->getClassMetadata($args->getObject()::class)->getIdentifierValues($args->getObject());
        if (count($identifier) === 1) {
            $value = reset($identifier);
            if (is_int($value) || is_string($value)) $this->removedIds[$args->getObject()] = (string) $value;
        }
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->record($args->getObject(), $args->getObjectManager(), 'created');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->record($args->getObject(), $args->getObjectManager(), 'updated');
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $id = $this->removedIds[$args->getObject()] ?? null;
        unset($this->removedIds[$args->getObject()]);
        $this->record($args->getObject(), $args->getObjectManager(), 'deleted', $id);
    }

    private function record(object $entity, EntityManagerInterface $manager, string $action, ?string $deletedId = null): void
    {
        if ($entity instanceof AdminChangeLog) return;
        $metadata = $manager->getClassMetadata($entity::class);
        $entityType = (new \ReflectionClass($metadata->getName()))->getShortName();
        if (!in_array($entityType, self::AUDITED_TYPES, true)) return;

        $request = $this->requests->getCurrentRequest();
        $actor = $this->security->getUser();
        if ($request === null || !str_starts_with($request->getPathInfo(), '/api/v1/admin/') || !$actor instanceof User || $actor->getId() === null) return;

        $identifier = $metadata->getIdentifierValues($entity);
        $entityId = $deletedId ?? ($identifier === [] || count($identifier) !== 1 ? null : reset($identifier));
        $tenantId = $this->connection->fetchOne('SELECT id FROM tenant WHERE slug = ?', [$this->tenantSlug]);
        if ($tenantId === false) throw new \LogicException('The configured audit tenant does not exist.');

        // DBAL writes in Doctrine's current flush transaction, so failed entity writes do not leave audit entries.
        // No request body, chat content, passwords or changed field values are stored.
        $this->connection->insert('admin_change_log', [
            'tenant_id' => (int) $tenantId,
            'actor_user_id' => $actor->getId(),
            'actor_name' => $actor->getDisplayName(),
            'entity_type' => $entityType,
            'entity_id' => is_int($entityId) || is_string($entityId) ? (string) $entityId : null,
            'action' => $action,
            'request_path' => mb_substr($request->getPathInfo(), 0, 255),
            'occurred_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);
    }
}
