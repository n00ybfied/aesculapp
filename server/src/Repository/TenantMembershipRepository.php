<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TenantMembership;
use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TenantMembership>
 */
final class TenantMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantMembership::class);
    }

    public function hasActiveMembershipFor(User $user, Tenant $tenant): bool
    {
        return null !== $this->createQueryBuilder('membership')
            ->select('membership.id')
            ->andWhere('membership.user = :user')
            ->andWhere('membership.tenant = :tenant')
            ->setParameter('user', $user)
            ->setParameter('tenant', $tenant)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
