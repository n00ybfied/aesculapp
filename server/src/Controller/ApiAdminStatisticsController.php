<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\{ActiveRedemption, AppAnalyticsEvent, Coupon, CouponRedemption, NewsPost, PointAccount, Reward, TenantMembership, User};
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAdminStatisticsController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly ActiveTenantProvider $tenant,
        private readonly TenantMembershipRepository $memberships,
    ) {
    }

    #[Route('/api/v1/admin/statistics', methods: ['GET'])]
    public function overview(): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $tenant = $this->tenant->get();
        $from = (new \DateTimeImmutable('today'))->modify('-29 days');
        $customerRole = '%ROLE_CUSTOMER%';
        $memberships = $this->entityManager->createQueryBuilder()
            ->select('membership')
            ->from(TenantMembership::class, 'membership')
            ->where('membership.tenant = :tenant')
            ->andWhere('membership.roles LIKE :customerRole')
            ->setParameter('tenant', $tenant)
            ->setParameter('customerRole', $customerRole)
            ->getQuery()
            ->getResult();

        $dailyNew = array_fill_keys($this->dates($from), 0);
        $totalUsers = 0;
        foreach ($memberships as $membership) {
            $created = $membership->getCreatedAt();
            if ($created < $from) {
                ++$totalUsers;
                continue;
            }
            ++$totalUsers;
            $day = $created->format('Y-m-d');
            if (array_key_exists($day, $dailyNew)) {
                ++$dailyNew[$day];
            }
        }
        $runningTotal = $totalUsers - array_sum($dailyNew);
        $userTimeline = [];
        foreach ($dailyNew as $day => $newUsers) {
            $runningTotal += $newUsers;
            $userTimeline[] = ['date' => $day, 'totalUsers' => $runningTotal, 'newUsers' => $newUsers];
        }

        $viewRows = $this->entityManager->createQueryBuilder()
            ->select('event.subjectType AS type, event.subjectId AS id, COUNT(event.id) AS views')
            ->from(AppAnalyticsEvent::class, 'event')
            ->where('event.tenant = :tenant')
            ->andWhere('event.eventType = :view')
            ->andWhere('event.createdAt >= :from')
            ->groupBy('event.subjectType, event.subjectId')
            ->orderBy('views', 'DESC')
            ->setMaxResults(8)
            ->setParameter('tenant', $tenant)
            ->setParameter('view', 'view_item')
            ->setParameter('from', $from)
            ->getQuery()
            ->getArrayResult();

        $couponRedemptions = $this->entityManager->createQueryBuilder()
            ->select('coupon.title AS title, COUNT(redemption.id) AS redemptions')
            ->from(CouponRedemption::class, 'redemption')
            ->join('redemption.coupon', 'coupon')
            ->where('redemption.tenant = :tenant')
            ->andWhere('redemption.status = :status')
            ->groupBy('coupon.id, coupon.title')
            ->orderBy('redemptions', 'DESC')
            ->setMaxResults(5)
            ->setParameter('tenant', $tenant)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getArrayResult();
        $rewardRedemptions = $this->entityManager->createQueryBuilder()
            ->select('redemption.summary AS title, COUNT(redemption.id) AS redemptions')
            ->from(ActiveRedemption::class, 'redemption')
            ->join('redemption.account', 'account')
            ->where('account.tenant = :tenant')
            ->andWhere('redemption.status = :status')
            ->groupBy('redemption.summary')
            ->orderBy('redemptions', 'DESC')
            ->setMaxResults(5)
            ->setParameter('tenant', $tenant)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getArrayResult();

        $durationSeconds = (int) $this->entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(event.durationSeconds), 0)')
            ->from(AppAnalyticsEvent::class, 'event')
            ->where('event.tenant = :tenant')
            ->andWhere('event.eventType = :eventType')
            ->andWhere('event.createdAt >= :from')
            ->setParameter('tenant', $tenant)
            ->setParameter('eventType', 'user_engagement')
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();
        $activeUsers = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT event.user)')
            ->from(AppAnalyticsEvent::class, 'event')
            ->where('event.tenant = :tenant')
            ->andWhere('event.createdAt >= :from')
            ->setParameter('tenant', $tenant)
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();
        $topCouponCustomers = $this->entityManager->createQueryBuilder()
            ->select('customer.id AS id, customer.displayName AS title, COUNT(redemption.id) AS total')
            ->from(CouponRedemption::class, 'redemption')
            ->join('redemption.customer', 'customer')
            ->where('redemption.tenant = :tenant')
            ->andWhere('redemption.status = :status')
            ->groupBy('customer.id, customer.displayName')
            ->setParameter('tenant', $tenant)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getArrayResult();
        $topRewardCustomers = $this->entityManager->createQueryBuilder()
            ->select('customer.id AS id, customer.displayName AS title, COUNT(redemption.id) AS total')
            ->from(ActiveRedemption::class, 'redemption')
            ->join('redemption.account', 'account')
            ->join('account.owner', 'customer')
            ->where('account.tenant = :tenant')
            ->andWhere('redemption.status = :status')
            ->groupBy('customer.id, customer.displayName')
            ->setParameter('tenant', $tenant)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getArrayResult();
        $topSessionUsers = $this->entityManager->createQueryBuilder()
            ->select('customer.id AS id, customer.displayName AS title, SUM(event.durationSeconds) AS seconds')
            ->from(AppAnalyticsEvent::class, 'event')
            ->join('event.user', 'customer')
            ->where('event.tenant = :tenant')
            ->andWhere('event.eventType = :eventType')
            ->andWhere('event.createdAt >= :from')
            ->groupBy('customer.id, customer.displayName')
            ->orderBy('seconds', 'DESC')
            ->setMaxResults(5)
            ->setParameter('tenant', $tenant)
            ->setParameter('eventType', 'user_engagement')
            ->setParameter('from', $from)
            ->getQuery()
            ->getArrayResult();

        return new JsonResponse([
            'periodDays' => 30,
            'users' => ['total' => $totalUsers, 'timeline' => $userTimeline],
            'catalog' => [
                'news' => $this->count(NewsPost::class, $tenant),
                'rewards' => $this->count(Reward::class, $tenant),
                'coupons' => $this->count(Coupon::class, $tenant),
            ],
            'engagement' => [
                'activeUsers' => $activeUsers,
                'totalMinutes' => (int) round($durationSeconds / 60),
                'averageMinutesPerActiveUser' => $activeUsers === 0 ? 0 : round($durationSeconds / 60 / $activeUsers, 1),
                'mostViewed' => array_map(fn (array $row) => ['title' => $this->subjectTitle((string) $row['type'], (int) $row['id']), 'type' => $row['type'], 'views' => (int) $row['views']], $viewRows),
                'longestSessions' => array_map(static fn (array $row) => ['title' => $row['title'], 'minutes' => (int) round((int) $row['seconds'] / 60)], $topSessionUsers),
            ],
            'redemptions' => [
                'rewards' => $this->rows($rewardRedemptions),
                'coupons' => $this->rows($couponRedemptions),
                'totalRewards' => $this->countRewardRedemptions($tenant),
                'totalCoupons' => $this->countCouponRedemptions($tenant),
                'topCustomers' => $this->combineCustomerTotals($topCouponCustomers, $topRewardCustomers),
            ],
        ]);
    }

    private function isAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) return false;
        $membership = $this->memberships->findForUserAndTenant($user, $this->tenant->get());
        return $membership !== null && array_intersect(self::ADMIN_ROLES, $membership->getRoles()) !== [];
    }

    /** @return list<string> */
    private function dates(\DateTimeImmutable $from): array
    {
        $dates = [];
        for ($date = $from; $date <= new \DateTimeImmutable('today'); $date = $date->modify('+1 day')) $dates[] = $date->format('Y-m-d');
        return $dates;
    }

    private function count(string $entityClass, object $tenant): int
    {
        return (int) $this->entityManager->createQueryBuilder()->select('COUNT(item.id)')->from($entityClass, 'item')->where('item.tenant = :tenant')->setParameter('tenant', $tenant)->getQuery()->getSingleScalarResult();
    }

    /** @param list<array{title:string,redemptions:string}> $rows @return list<array{title:string,redemptions:int}> */
    private function rows(array $rows): array
    {
        return array_map(static fn (array $row) => ['title' => $row['title'], 'redemptions' => (int) $row['redemptions']], $rows);
    }

    private function countRewardRedemptions(object $tenant): int
    {
        return (int) $this->entityManager->createQueryBuilder()->select('COUNT(redemption.id)')->from(ActiveRedemption::class, 'redemption')->join('redemption.account', 'account')->where('account.tenant = :tenant')->andWhere('redemption.status = :status')->setParameter('tenant', $tenant)->setParameter('status', 'active')->getQuery()->getSingleScalarResult();
    }

    private function countCouponRedemptions(object $tenant): int
    {
        return (int) $this->entityManager->createQueryBuilder()->select('COUNT(redemption.id)')->from(CouponRedemption::class, 'redemption')->where('redemption.tenant = :tenant')->andWhere('redemption.status = :status')->setParameter('tenant', $tenant)->setParameter('status', 'active')->getQuery()->getSingleScalarResult();
    }

    private function subjectTitle(string $type, int $id): string
    {
        $class = match ($type) { 'news' => NewsPost::class, 'reward' => Reward::class, 'coupon' => Coupon::class, default => null };
        if ($class === null) return 'Unbekannter Inhalt';
        $item = $this->entityManager->getRepository($class)->find($id);
        if ($item === null || $item->getTenant() !== $this->tenant->get()) return 'Nicht mehr verfügbar';
        return $item->getTitle();
    }

    /** @param list<array{id:string,title:string,total:string}> $couponRows @param list<array{id:string,title:string,total:string}> $rewardRows @return list<array{title:string,redemptions:int}> */
    private function combineCustomerTotals(array $couponRows, array $rewardRows): array
    {
        $customers = [];
        foreach ([$couponRows, $rewardRows] as $rows) {
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $customers[$id] ??= ['title' => $row['title'], 'redemptions' => 0];
                $customers[$id]['redemptions'] += (int) $row['total'];
            }
        }
        usort($customers, static fn (array $left, array $right): int => $right['redemptions'] <=> $left['redemptions']);
        return array_slice(array_values($customers), 0, 5);
    }
}
