<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{FamilyConnection, PointAccount, PointTransaction, Tenant, User};
use Doctrine\ORM\EntityManagerInterface;

final class FamilyPointSharingService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** @return list<User> */
    public function members(User $user, Tenant $tenant): array
    {
        /** @var list<FamilyConnection> $connections */
        $connections = $this->entityManager->getRepository(FamilyConnection::class)->findBy([
            'tenant' => $tenant,
            'status' => 'accepted',
            'pointSharingStatus' => 'accepted',
        ]);
        $usersById = [$user->getId() => $user];
        $queue = [$user];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($connections as $connection) {
                if (!$connection->isParticipant($current)) continue;
                $other = $connection->other($current);
                if (!isset($usersById[$other->getId()])) {
                    $usersById[$other->getId()] = $other;
                    $queue[] = $other;
                }
            }
        }

        return array_values($usersById);
    }

    /** @return list<PointAccount> */
    public function accounts(User $user, Tenant $tenant): array
    {
        /** @var list<PointAccount> $accounts */
        $accounts = $this->entityManager->getRepository(PointAccount::class)->findBy([
            'tenant' => $tenant,
            'owner' => $this->members($user, $tenant),
        ]);
        usort($accounts, static fn (PointAccount $left, PointAccount $right): int => ($left->getId() ?? 0) <=> ($right->getId() ?? 0));

        return $accounts;
    }

    public function balance(PointAccount $account): int
    {
        return (int) $this->entityManager->createQuery('SELECT COALESCE(SUM(transaction.points), 0) FROM App\\Entity\\PointTransaction transaction WHERE transaction.account = :account')
            ->setParameter('account', $account)
            ->getSingleScalarResult();
    }

    public function combinedBalance(User $user, Tenant $tenant): int
    {
        $accounts = $this->accounts($user, $tenant);
        if ($accounts === []) return 0;

        return (int) $this->entityManager->createQuery('SELECT COALESCE(SUM(transaction.points), 0) FROM App\\Entity\\PointTransaction transaction WHERE transaction.account IN (:accounts)')
            ->setParameter('accounts', $accounts)
            ->getSingleScalarResult();
    }

    /**
     * Splits a redemption cost proportionally across positive personal balances.
     * Any rounding remainder is debited from the account with the largest
     * current balance first, so all ledger entries remain whole numbers.
     *
     * @param list<PointAccount> $accounts
     * @return array<int, int> account id => points to debit
     */
    public function proportionalDebits(array $accounts, int $points): array
    {
        $balances = [];
        foreach ($accounts as $account) {
            $balance = $this->balance($account);
            if ($balance > 0 && $account->getId() !== null) $balances[$account->getId()] = ['account' => $account, 'balance' => $balance];
        }
        $available = array_sum(array_column($balances, 'balance'));
        if ($points < 1 || $points > $available) throw new \LogicException('Insufficient shared points.');

        $debits = [];
        $allocated = 0;
        foreach ($balances as $id => $entry) {
            $exact = $points * $entry['balance'] / $available;
            $debits[$id] = (int) floor($exact);
            $allocated += $debits[$id];
        }
        $largestBalancesFirst = array_keys($balances);
        usort($largestBalancesFirst, fn (int $left, int $right): int => $balances[$right]['balance'] <=> $balances[$left]['balance'] ?: $left <=> $right);
        while ($allocated < $points) {
            foreach ($largestBalancesFirst as $id) {
                if ($allocated >= $points) break;
                if ($debits[$id] >= $balances[$id]['balance']) continue;
                $debits[$id]++;
                $allocated++;
            }
        }

        return $debits;
    }
}
