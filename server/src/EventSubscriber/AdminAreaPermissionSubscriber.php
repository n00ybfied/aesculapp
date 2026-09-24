<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\AdminAreaPermissions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class AdminAreaPermissionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly AdminAreaPermissions $permissions,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'check'];
    }

    public function check(ControllerEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api/v1/admin/')) return;
        $area = $this->permissions->areaForPath($path);
        if ($area === null) return;

        $user = $this->security->getUser();
        $membership = $user instanceof User ? $this->memberships->findForUserAndTenant($user, $this->tenant->get()) : null;
        if ($membership === null || !$this->permissions->can($membership, $area)) {
            throw new AccessDeniedHttpException('Für diesen Bereich fehlt die Berechtigung.');
        }
        if (preg_match('~^/api/v1/admin/appointments/\d+/chats?(?:/|$)~', $path) && !$this->permissions->can($membership, 'chat')) {
            throw new AccessDeniedHttpException('Für den verknüpften Chat fehlt die Berechtigung.');
        }
    }
}
