<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailVerificationToken;
use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<EmailVerificationToken> */
final class EmailVerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, EmailVerificationToken::class); }

    public function findUsableByTokenHash(string $tokenHash): ?EmailVerificationToken
    {
        return $this->createQueryBuilder('token')
            ->andWhere('token.tokenHash = :tokenHash')->andWhere('token.usedAt IS NULL')->andWhere('token.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)->setParameter('now', new \DateTimeImmutable())
            ->getQuery()->getOneOrNullResult();
    }

    public function findMostRecentFor(User $user, Tenant $tenant): ?EmailVerificationToken
    {
        return $this->createQueryBuilder('token')
            ->andWhere('token.user = :user')
            ->andWhere('token.tenant = :tenant')
            ->setParameter('user', $user)
            ->setParameter('tenant', $tenant)
            ->orderBy('token.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function invalidatePendingFor(User $user, Tenant $tenant): void
    {
        $this->createQueryBuilder('token')
            ->update()
            ->set('token.usedAt', ':now')
            ->andWhere('token.user = :user')
            ->andWhere('token.tenant = :tenant')
            ->andWhere('token.usedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->execute();
    }
}
