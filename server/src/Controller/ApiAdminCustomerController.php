<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ActiveRedemption;
use App\Entity\PointAccount;
use App\Entity\PointTransaction;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\PointAccountProvisioner;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAdminCustomerController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly EntityManagerInterface $entityManager,
        private readonly PointAccountProvisioner $accountProvisioner,
    ) {
    }

    #[Route('/api/v1/admin/customers/{id}', name: 'api_v1_admin_customer_get', methods: ['GET'])]
    public function get(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $customer = $this->findCustomer($id);
        if ($customer === null) {
            return new JsonResponse(['message' => 'Customer not found.'], Response::HTTP_NOT_FOUND);
        }

        $account = $this->entityManager->getRepository(PointAccount::class)->findOneBy([
            'tenant' => $this->activeTenant->get(),
            'owner' => $customer,
        ]);

        return new JsonResponse([
            'customer' => $this->serializeCustomer($customer, $request),
            'points' => $account instanceof PointAccount ? $this->balance($account) : 0,
            'transactions' => $account instanceof PointAccount ? $this->transactions($account) : [],
        ]);
    }

    #[Route('/api/v1/admin/customers/{id}/point-transactions', name: 'api_v1_admin_customer_credit', methods: ['POST'])]
    public function credit(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $customer = $this->findCustomer($id);
        if ($customer === null) {
            return new JsonResponse(['message' => 'Customer not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Invalid request body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $points = filter_var($data['points'] ?? null, FILTER_VALIDATE_INT);
        $label = trim((string) ($data['label'] ?? ''));
        if ($points === false || $points < 1 || $points > 100000 || mb_strlen($label) < 3 || mb_strlen($label) > 180) {
            return new JsonResponse(['message' => 'Invalid points or reason.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $transaction = $this->entityManager->wrapInTransaction(function () use ($customer, $points, $label): PointTransaction {
            $account = $this->accountProvisioner->getOrCreate($this->activeTenant->get(), $customer);
            $transaction = new PointTransaction($account, $points, 'manual_credit', 'Manuelle Aufladung: '.$label);
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            return $transaction;
        });

        return new JsonResponse(['transaction' => $this->serializeTransaction($transaction)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/customers/{customerId}/point-transactions/{transactionId}/reverse', name: 'api_v1_admin_customer_transaction_reverse', methods: ['POST'])]
    public function reverse(int $customerId, int $transactionId, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $customer = $this->findCustomer($customerId);
        if ($customer === null) {
            return new JsonResponse(['message' => 'Customer not found.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = $request->getContent() === '' ? [] : $request->toArray();
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Invalid request body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) > 120) {
            return new JsonResponse(['message' => 'Invalid cancellation reason.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $reversal = $this->entityManager->wrapInTransaction(function () use ($customer, $transactionId, $reason): PointTransaction|JsonResponse {
                $transaction = $this->entityManager->find(PointTransaction::class, $transactionId, LockMode::PESSIMISTIC_WRITE);
                if (!$transaction instanceof PointTransaction || $transaction->getAccount()->getTenant() !== $this->activeTenant->get() || $transaction->getAccount()->getOwner()->getId() !== $customer->getId()) {
                    return new JsonResponse(['message' => 'Transaction not found.'], Response::HTTP_NOT_FOUND);
                }
                if ($transaction->getType() === 'admin_reversal') {
                    return new JsonResponse(['message' => 'A reversal cannot be reversed.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                if ($this->entityManager->getRepository(PointTransaction::class)->findOneBy(['reversalOf' => $transaction]) instanceof PointTransaction) {
                    return new JsonResponse(['message' => 'Transaction has already been reversed.'], Response::HTTP_CONFLICT);
                }
                if ($transaction->getType() === 'redemption' && $this->hasActiveRedemption($transaction->getAccount())) {
                    return new JsonResponse(['message' => 'Active redemptions must be cancelled from the redemption overview.'], Response::HTTP_CONFLICT);
                }

                $label = 'Storno von Buchung #'.$transaction->getId().': '.$transaction->getLabel();
                if ($reason !== '') {
                    $label .= ' ('.$reason.')';
                }
                $reversal = new PointTransaction($transaction->getAccount(), -$transaction->getPoints(), 'admin_reversal', $label);
                $reversal->setReversalOf($transaction);
                $this->entityManager->persist($reversal);
                $this->entityManager->flush();

                return $reversal;
            });
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return new JsonResponse(['message' => 'Transaction has already been reversed.'], Response::HTTP_CONFLICT);
        }

        if ($reversal instanceof JsonResponse) {
            return $reversal;
        }

        return new JsonResponse(['transaction' => $this->serializeTransaction($reversal)], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/admin/customers', name: 'api_v1_admin_customers_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $tenant = $this->activeTenant->get();
        $page = max(1, $request->query->getInt('page', 1));
        $pageSize = min(50, max(1, $request->query->getInt('pageSize', 20)));
        $query = trim((string) $request->query->get('query', ''));
        $builder = $this->memberships->createQueryBuilder('membership')
            ->join('membership.user', 'user')
            ->andWhere('membership.tenant = :tenant')
            ->andWhere('membership.roles LIKE :customerRole')
            ->setParameter('tenant', $tenant)
            ->setParameter('customerRole', '%ROLE_CUSTOMER%');
        if ($query !== '') {
            $builder->andWhere('user.displayName LIKE :query OR user.email LIKE :query OR user.id = :id')
                ->setParameter('query', '%'.$query.'%')
                ->setParameter('id', ctype_digit($query) ? (int) $query : -1);
        }
        $total = (int) (clone $builder)->select('COUNT(membership.id)')->getQuery()->getSingleScalarResult();
        /** @var list<TenantMembership> $memberships */
        $memberships = $builder->orderBy('user.displayName', 'ASC')->setFirstResult(($page - 1) * $pageSize)->setMaxResults($pageSize)->getQuery()->getResult();
        $accounts = $this->entityManager->getRepository(PointAccount::class)->findBy(['tenant' => $tenant]);
        $accountsByUserId = [];
        foreach ($accounts as $account) {
            $ownerId = $account->getOwner()->getId();
            if ($ownerId !== null) {
                $accountsByUserId[$ownerId] = $account;
            }
        }
        $balances = $this->balances($accountsByUserId);
        $origin = $request->getSchemeAndHttpHost();

        return new JsonResponse(['customers' => array_map(static function (TenantMembership $membership) use ($accountsByUserId, $balances, $origin): array {
            $user = $membership->getUser();
            $account = $accountsByUserId[$user->getId() ?? 0] ?? null;
            $accountId = $account?->getId();

            return ['id' => $user->getId(), 'displayName' => $user->getDisplayName(), 'email' => $user->getEmail(), 'profileImageUrl' => $user->getProfileImagePath() === null ? null : $origin.$user->getProfileImagePath(), 'points' => $accountId === null ? 0 : ($balances[$accountId] ?? 0)];
        }, $memberships), 'page' => $page, 'total' => $total, 'totalPages' => (int) ceil($total / $pageSize)]);
    }

    private function findCustomer(int $id): ?User
    {
        $membership = $this->memberships->createQueryBuilder('membership')
            ->join('membership.user', 'user')
            ->andWhere('membership.tenant = :tenant')
            ->andWhere('membership.roles LIKE :customerRole')
            ->andWhere('user.id = :id')
            ->setParameter('tenant', $this->activeTenant->get())
            ->setParameter('customerRole', '%ROLE_CUSTOMER%')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $membership instanceof TenantMembership ? $membership->getUser() : null;
    }

    /** @return list<array{id: int|null, points: int, type: string, label: string, createdAt: string, canReverse: bool, reversed: bool}> */
    private function transactions(PointAccount $account): array
    {
        /** @var list<PointTransaction> $transactions */
        $transactions = $this->entityManager->getRepository(PointTransaction::class)->findBy(['account' => $account], ['createdAt' => 'DESC'], 50);
        $reversedIds = [];
        if ($transactions !== []) {
            $reversals = $this->entityManager->createQuery('SELECT IDENTITY(transaction.reversalOf) AS originalId FROM App\\Entity\\PointTransaction transaction WHERE transaction.reversalOf IN (:transactions)')
                ->setParameter('transactions', $transactions)
                ->getScalarResult();
            foreach ($reversals as $reversal) {
                $reversedIds[(int) $reversal['originalId']] = true;
            }
        }

        return array_map(fn (PointTransaction $transaction): array => $this->serializeTransaction($transaction, isset($reversedIds[$transaction->getId() ?? 0])), $transactions);
    }

    /** @return array<int, int> */
    private function balances(array $accountsByUserId): array
    {
        if ($accountsByUserId === []) {
            return [];
        }
        $rows = $this->entityManager->createQuery('SELECT IDENTITY(transaction.account) AS accountId, COALESCE(SUM(transaction.points), 0) AS points FROM App\\Entity\\PointTransaction transaction WHERE transaction.account IN (:accounts) GROUP BY transaction.account')
            ->setParameter('accounts', array_values($accountsByUserId))
            ->getArrayResult();
        $balances = [];
        foreach ($rows as $row) {
            $balances[(int) $row['accountId']] = (int) $row['points'];
        }

        return $balances;
    }

    /** @return array{id: int|null, points: int, type: string, label: string, createdAt: string, canReverse: bool, reversed: bool} */
    private function serializeTransaction(PointTransaction $transaction, bool $reversed = false): array
    {
        return ['id' => $transaction->getId(), 'points' => $transaction->getPoints(), 'type' => $transaction->getType(), 'label' => $transaction->getLabel(), 'createdAt' => $transaction->getCreatedAt()->format(DATE_ATOM), 'reversed' => $reversed, 'canReverse' => !$reversed && $transaction->getType() !== 'admin_reversal'];
    }

    /** @return array{id: int|null, username: string, displayName: string, email: string, phone: string|null, streetAddress: string|null, postalCode: string|null, city: string|null, profileImageUrl: string|null} */
    private function serializeCustomer(User $user, Request $request): array
    {
        return ['id' => $user->getId(), 'username' => $user->getUsername(), 'displayName' => $user->getDisplayName(), 'email' => $user->getEmail(), 'phone' => $user->getPhone(), 'streetAddress' => $user->getStreetAddress(), 'postalCode' => $user->getPostalCode(), 'city' => $user->getCity(), 'profileImageUrl' => $user->getProfileImagePath() === null ? null : $request->getSchemeAndHttpHost().$user->getProfileImagePath()];
    }

    private function balance(PointAccount $account): int
    {
        return (int) $this->entityManager->createQuery('SELECT COALESCE(SUM(transaction.points), 0) FROM App\\Entity\\PointTransaction transaction WHERE transaction.account = :account')->setParameter('account', $account)->getSingleScalarResult();
    }

    private function hasActiveRedemption(PointAccount $account): bool
    {
        return $this->entityManager->createQuery('SELECT COUNT(redemption.id) FROM App\\Entity\\ActiveRedemption redemption WHERE redemption.account = :account AND redemption.status = :status AND redemption.validUntil > :now')->setParameter('account', $account)->setParameter('status', 'active')->setParameter('now', new \DateTimeImmutable())->getSingleScalarResult() > 0;
    }

    private function isAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());

        return $membership !== null && [] !== array_intersect(self::ADMIN_ROLES, $membership->getRoles());
    }
}
