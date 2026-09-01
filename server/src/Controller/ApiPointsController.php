<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PointAccount;
use App\Entity\PointTransaction;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiPointsController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/v1/rewards/balance', name: 'api_v1_rewards_balance', methods: ['GET'])]
    public function balance(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->memberships->hasActiveMembershipFor($user, $this->tenant->get())) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $account = $this->entityManager->getRepository(PointAccount::class)->findOneBy(['owner' => $user, 'tenant' => $this->tenant->get()]);
        if (!$account instanceof PointAccount) {
            return new JsonResponse(['availablePoints' => 0]);
        }

        $points = $this->entityManager->createQuery('SELECT COALESCE(SUM(transaction.points), 0) FROM App\Entity\PointTransaction transaction WHERE transaction.account = :account')
            ->setParameter('account', $account)
            ->getSingleScalarResult();

        return new JsonResponse(['availablePoints' => (int) $points]);
    }

    #[Route('/api/v1/rewards/transactions', name: 'api_v1_rewards_transactions', methods: ['GET'])]
    public function transactions(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->memberships->hasActiveMembershipFor($user, $this->tenant->get())) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = min(50, max(1, $request->query->getInt('pageSize', 10)));
        $account = $this->entityManager->getRepository(PointAccount::class)->findOneBy(['owner' => $user, 'tenant' => $this->tenant->get()]);
        if (!$account instanceof PointAccount) {
            return new JsonResponse(['transactions' => [], 'page' => 1, 'pageSize' => $pageSize, 'total' => 0, 'totalPages' => 0]);
        }

        $total = (int) $this->entityManager->createQuery('SELECT COUNT(transaction.id) FROM App\Entity\PointTransaction transaction WHERE transaction.account = :account')
            ->setParameter('account', $account)
            ->getSingleScalarResult();
        $totalPages = (int) ceil($total / $pageSize);
        $page = min($page, max(1, $totalPages));
        $transactions = $this->entityManager->getRepository(PointTransaction::class)->findBy(
            ['account' => $account],
            ['createdAt' => 'DESC'],
            $pageSize,
            ($page - 1) * $pageSize,
        );

        return new JsonResponse([
            'transactions' => array_map(static fn (PointTransaction $transaction) => [
                'id' => $transaction->getId(),
                'label' => $transaction->getLabel(),
                'points' => $transaction->getPoints(),
                'createdAt' => $transaction->getCreatedAt()->format(DATE_ATOM),
            ], $transactions),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }
}
