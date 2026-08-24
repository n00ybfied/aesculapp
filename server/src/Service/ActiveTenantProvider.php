<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

final class ActiveTenantProvider
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $tenantSlug,
    ) {
    }

    public function get(): Tenant
    {
        $tenant = $this->entityManager->getRepository(Tenant::class)->findOneBy([
            'slug' => $this->tenantSlug,
            'isActive' => true,
        ]);

        if (!$tenant instanceof Tenant) {
            throw new \LogicException('The configured application tenant does not exist or is inactive.');
        }

        return $tenant;
    }
}
