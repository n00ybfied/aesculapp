<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PasswordResetToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
final class PasswordResetTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findUsableByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->createQueryBuilder('resetToken')
            ->andWhere('resetToken.tokenHash = :tokenHash')
            ->andWhere('resetToken.usedAt IS NULL')
            ->andWhere('resetToken.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function invalidateForUser(User $user): void
    {
        $this->createQueryBuilder('resetToken')
            ->update()
            ->set('resetToken.usedAt', ':now')
            ->andWhere('resetToken.user = :user')
            ->andWhere('resetToken.usedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
