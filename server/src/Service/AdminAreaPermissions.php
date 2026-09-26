<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TenantMembership;

final class AdminAreaPermissions
{
    public const AREAS = ['dashboard', 'slider', 'statistics', 'chat', 'news', 'media', 'rewards', 'coupons', 'appointments', 'redemptions', 'customers', 'users', 'settings'];
    public const DEFAULT_STAFF_AREAS = ['dashboard', 'slider', 'chat', 'news', 'media', 'rewards', 'coupons', 'appointments', 'redemptions', 'customers'];

    public function can(TenantMembership $membership, string $area): bool
    {
        if (!in_array($area, self::AREAS, true)) return false;
        if (in_array('ROLE_TENANT_ADMIN', $membership->getRoles(), true)) {
            return true;
        }

        return in_array('ROLE_TENANT_STAFF', $membership->getRoles(), true)
            && ($membership->getPermissions() === null || in_array($area, $membership->getPermissions(), true));
    }

    public function areaForPath(string $path): ?string
    {
        if ($path === '/api/v1/admin/audit') return null; // The audit endpoint checks the requested entity's area itself.
        if (preg_match('~^/api/v1/admin/(auth(?:/|$)|invitations/accept$|appointments/mine(?:/|$))~', $path)) {
            return null;
        }

        if (str_starts_with($path, '/api/v1/admin/coupons/redemptions')) return 'redemptions';
        if (str_starts_with($path, '/api/v1/admin/dashboard/slides')) return 'slider';

        $prefix = explode('/', substr($path, strlen('/api/v1/admin/')))[0];
        return match ($prefix) {
            'dashboard' => 'dashboard', 'statistics' => 'statistics', 'chat' => 'chat',
            'news' => 'news', 'media' => 'media', 'rewards' => 'rewards', 'coupons' => 'coupons',
            'appointments' => 'appointments', 'redemptions' => 'redemptions',
            'customers', 'point-transactions' => 'customers', 'users' => 'users',
            'settings' => 'settings', default => 'unknown',
        };
    }
}
