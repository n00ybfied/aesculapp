<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AppointmentBlock;
use App\Entity\AppointmentType;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

final class AppointmentBlocker
{
    public function __construct(private readonly EntityManagerInterface $entityManager) {}

    public function blocks(Tenant $tenant, AppointmentType $type, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): bool
    {
        $blocks = $this->entityManager->createQueryBuilder()
            ->select('block')
            ->from(AppointmentBlock::class, 'block')
            ->where('block.tenant = :tenant')
            ->andWhere('(block.type IS NULL OR block.type = :type)')
            ->andWhere('block.startsOn <= :day')
            ->andWhere('block.endsOn >= :day')
            ->setParameter('tenant', $tenant)
            ->setParameter('type', $type)
            ->setParameter('day', $startsAt->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        foreach ($blocks as $block) {
            if ($block->getStartsAt() === null || $block->getEndsAt() === null) {
                return true;
            }

            $blockStart = new \DateTimeImmutable($startsAt->format('Y-m-d').' '.$block->getStartsAt());
            $blockEnd = new \DateTimeImmutable($startsAt->format('Y-m-d').' '.$block->getEndsAt());
            if ($blockStart < $endsAt && $blockEnd > $startsAt) {
                return true;
            }
        }

        return false;
    }
}
