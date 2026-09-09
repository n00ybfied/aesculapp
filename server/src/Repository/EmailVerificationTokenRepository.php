<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailVerificationToken;
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
}
