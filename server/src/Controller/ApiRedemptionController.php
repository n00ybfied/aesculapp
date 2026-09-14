<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{ActiveRedemption, PointAccount, PointTransaction, Reward, User};
use App\Repository\TenantMembershipRepository;
use App\Service\{ActiveTenantProvider, FamilyPointSharingService, PointAccountProvisioner};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class ApiRedemptionController
{
    /** @var list<string> */
    private const ADMIN = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly PointAccountProvisioner $pointAccounts,
        private readonly FamilyPointSharingService $familyPoints,
    ) {
    }

    #[Route('/api/v1/rewards/redeem', methods: ['POST'])]
    public function redeem(Request $request): JsonResponse
    {
        $user = $this->customer();
        if (!$user instanceof User) return new JsonResponse(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        try {
            $selections = $request->toArray()['selections'] ?? [];
        } catch (\Throwable) {
            $selections = [];
        }
        if (!is_array($selections) || $selections === []) return new JsonResponse(['message' => 'Invalid selection.'], Response::HTTP_UNPROCESSABLE_ENTITY);

        try {
            return $this->em->wrapInTransaction(function () use ($user, $selections, $request): JsonResponse {
                $tenant = $this->tenant->get();
                $requesterAccount = $this->pointAccounts->getOrCreate($tenant, $user);
                $this->em->flush();
                $accounts = $this->familyPoints->accounts($user, $tenant);
                $isShared = count($accounts) > 1;
                foreach ($accounts as $account) $this->em->lock($account, LockMode::PESSIMISTIC_WRITE);
                if ($this->hasActiveRedemption($accounts)) return new JsonResponse(['message' => 'An active redemption already exists.'], Response::HTTP_CONFLICT);

                [$total, $titles] = $this->selection($selections);
                if ($total === null) return new JsonResponse(['message' => 'Invalid selection.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                if ($this->familyPoints->combinedBalance($user, $tenant) < $total) return new JsonResponse(['message' => 'Insufficient points.'], Response::HTTP_UNPROCESSABLE_ENTITY);

                $redemption = new ActiveRedemption($requesterAccount, $total, implode(', ', $titles));
                $this->em->persist($redemption);
                $this->em->flush();

                if ($isShared) {
                    $debits = $this->familyPoints->proportionalDebits($accounts, $total);
                    foreach ($accounts as $account) {
                        $debit = $debits[$account->getId() ?? 0] ?? 0;
                        if ($debit > 0) {
                            $this->em->persist(new PointTransaction($account, -$debit, 'family_redemption', 'Familien-Einlösung #'.$redemption->getId().': '.$redemption->getSummary()));
                        }
                    }
                    $tenant->lockFamilyPointSharing();
                } else {
                    $this->em->persist(new PointTransaction($requesterAccount, -$total, 'redemption', $redemption->getSummary()));
                }
                $this->em->flush();

                return new JsonResponse([
                    'redemption' => $this->serialize($redemption, $request),
                    'remainingPoints' => $this->familyPoints->combinedBalance($user, $tenant),
                ]);
            });
        } catch (\LogicException) {
            return new JsonResponse(['message' => 'Insufficient points.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Could not create redemption.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/v1/admin/redemptions/active', methods: ['GET'])]
    public function active(Request $request): JsonResponse
    {
        if (!$this->admin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $items = [];
        foreach ($this->em->getRepository(ActiveRedemption::class)->findBy(['status' => 'active']) as $item) {
            if ($item->isActive()) $items[] = $this->serialize($item, $request);
        }

        return new JsonResponse(['redemptions' => $items]);
    }

    #[Route('/api/v1/rewards/active', methods: ['GET'])]
    public function customerActive(Request $request): JsonResponse
    {
        $user = $this->customer();
        if (!$user instanceof User) return new JsonResponse(['message' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        $accounts = $this->familyPoints->accounts($user, $this->tenant->get());
        if ($accounts === []) return new JsonResponse(['redemption' => null]);
        foreach ($this->em->getRepository(ActiveRedemption::class)->findBy(['account' => $accounts, 'status' => 'active']) as $item) {
            if ($item->isActive()) return new JsonResponse(['redemption' => $this->serialize($item, $request)]);
        }

        return new JsonResponse(['redemption' => null]);
    }

    #[Route('/api/v1/admin/redemptions/{id}/cancel', methods: ['POST'])]
    public function cancel(int $id): Response
    {
        if (!$this->admin()) return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        $item = $this->em->getRepository(ActiveRedemption::class)->find($id);
        if (!$item instanceof ActiveRedemption || !$item->isActive()) return new JsonResponse(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);

        $item->cancel();
        $transactions = $this->em->createQuery('SELECT transaction FROM App\\Entity\\PointTransaction transaction WHERE transaction.type = :type AND transaction.label LIKE :label')
            ->setParameter('type', 'family_redemption')
            ->setParameter('label', 'Familien-Einlösung #'.$item->getId().':%')
            ->getResult();
        if ($transactions === []) {
            $transactions = [new PointTransaction($item->getAccount(), -$item->getPoints(), 'redemption_cancellation', 'Abbruch: '.$item->getSummary())];
        } else {
            $transactions = array_map(static fn (PointTransaction $transaction): PointTransaction => new PointTransaction($transaction->getAccount(), -$transaction->getPoints(), 'redemption_cancellation', 'Abbruch: '.$item->getSummary()), $transactions);
        }
        foreach ($transactions as $transaction) $this->em->persist($transaction);
        $this->em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** @return array{0: ?int, 1: list<string>} */
    private function selection(array $selections): array
    {
        $total = 0;
        $titles = [];
        foreach ($selections as $selection) {
            if (!is_array($selection)) return [null, []];
            $reward = $this->em->getRepository(Reward::class)->find((int) ($selection['rewardId'] ?? 0));
            $quantity = (int) ($selection['quantity'] ?? 0);
            if (!$reward instanceof Reward || $reward->getTenant() !== $this->tenant->get() || !$reward->isVisible() || $quantity < 1) return [null, []];
            $total += $reward->getRequiredPoints() * $quantity;
            $titles[] = $quantity.'× '.$reward->getTitle();
        }

        return [$total, $titles];
    }

    /** @param list<PointAccount> $accounts */
    private function hasActiveRedemption(array $accounts): bool
    {
        if ($accounts === []) return false;
        return $this->em->createQuery('SELECT COUNT(redemption.id) FROM App\\Entity\\ActiveRedemption redemption WHERE redemption.account IN (:accounts) AND redemption.status = :status AND redemption.validUntil > :now')
            ->setParameter('accounts', $accounts)
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTimeImmutable())
            ->getSingleScalarResult() > 0;
    }

    private function customer(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User && $this->memberships->hasActiveMembershipFor($user, $this->tenant->get()) ? $user : null;
    }

    private function admin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return false;
        $member = $this->memberships->findForUserAndTenant($user, $this->tenant->get());

        return $member !== null && [] !== array_intersect(self::ADMIN, $member->getRoles());
    }

    private function serialize(ActiveRedemption $redemption, Request $request): array
    {
        $user = $redemption->getAccount()->getOwner();
        return ['id' => $redemption->getId(), 'summary' => $redemption->getSummary(), 'points' => $redemption->getPoints(), 'validUntil' => $redemption->getValidUntil()->format(DATE_ATOM), 'customer' => $user->getDisplayName(), 'customerDetails' => ['id' => $user->getId(), 'displayName' => $user->getDisplayName(), 'username' => $user->getUsername(), 'profileImageUrl' => $user->getProfileImagePath() === null ? null : $request->getSchemeAndHttpHost().$user->getProfileImagePath()]];
    }
}
