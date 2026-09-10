<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PointAccount;
use App\Entity\PointTransaction;
use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class PointAccountProvisioner
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getOrCreate(Tenant $tenant, User $user): PointAccount
    {
        $account = $this->entityManager->getRepository(PointAccount::class)->findOneBy([
            'tenant' => $tenant,
            'owner' => $user,
        ]);

        if ($account instanceof PointAccount) {
            return $account;
        }

        $account = new PointAccount($tenant, $user);
        $this->entityManager->persist($account);

        if ($tenant->getInitialPoints() > 0) {
            $this->entityManager->persist(new PointTransaction(
                $account,
                $tenant->getInitialPoints(),
                'initial_credit',
                'Startguthaben',
            ));
        }

        return $account;
    }
}
