<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StaffInvitation;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<StaffInvitation> */
final class StaffInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StaffInvitation::class);
    }

    public function findUsableByTokenHash(string $tokenHash): ?StaffInvitation
    {
        return $this->createQueryBuilder('invitation')
            ->andWhere('invitation.tokenHash = :tokenHash')
            ->andWhere('invitation.acceptedAt IS NULL')
            ->andWhere('invitation.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<StaffInvitation> */
    public function findPendingForTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('invitation')
            ->andWhere('invitation.tenant = :tenant')
            ->andWhere('invitation.acceptedAt IS NULL')
            ->andWhere('invitation.expiresAt > :now')
            ->setParameter('tenant', $tenant)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('invitation.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
