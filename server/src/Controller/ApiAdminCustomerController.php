<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\PointAccount;
use App\Entity\PointTransaction;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
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
    ) {
    }

    #[Route('/api/v1/admin/customers/{id}', name: 'api_v1_admin_customer_get', methods: ['GET'])]
    public function get(int $id, Request $request): JsonResponse
    {
        if (!$this->isAdmin()) {
            return new JsonResponse(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $membership = $this->memberships->createQueryBuilder('membership')
            ->join('membership.user', 'user')
            ->andWhere('membership.tenant = :tenant')
            ->andWhere('user.id = :id')
            ->setParameter('tenant', $this->activeTenant->get())
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($membership === null) {
            return new JsonResponse(['message' => 'Customer not found.'], Response::HTTP_NOT_FOUND);
        }

        $user = $membership->getUser();
        return new JsonResponse(['customer' => [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'displayName' => $user->getDisplayName(),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone(),
            'streetAddress' => $user->getStreetAddress(),
            'postalCode' => $user->getPostalCode(),
            'city' => $user->getCity(),
            'profileImageUrl' => $user->getProfileImagePath() === null ? null : $request->getSchemeAndHttpHost().$user->getProfileImagePath(),
        ]]);
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
        $memberships = $builder->orderBy('user.displayName', 'ASC')->setFirstResult(($page - 1) * $pageSize)->setMaxResults($pageSize)->getQuery()->getResult();
        $accounts = $this->entityManager->getRepository(PointAccount::class)->findBy(['tenant' => $tenant]);
        $accountIdsByUserId = [];
        foreach ($accounts as $account) {
            $accountIdsByUserId[$account->getOwner()->getId() ?? 0] = $account->getId();
        }
        $balances = [];
        if ($accountIdsByUserId !== []) {
            $balanceRows = $this->entityManager->createQuery('SELECT IDENTITY(transaction.account) AS accountId, COALESCE(SUM(transaction.points), 0) AS points FROM App\\Entity\\PointTransaction transaction WHERE transaction.account IN (:accounts) GROUP BY transaction.account')
                ->setParameter('accounts', array_values($accountIdsByUserId))
                ->getArrayResult();
            foreach ($balanceRows as $balanceRow) {
                $balances[(int) $balanceRow['accountId']] = (int) $balanceRow['points'];
            }
        }

        $origin = $request->getSchemeAndHttpHost();
        return new JsonResponse(['customers' => array_map(static function (TenantMembership $membership) use ($accountIdsByUserId, $balances, $origin): array {
            $user = $membership->getUser();
            $accountId = $accountIdsByUserId[$user->getId() ?? 0] ?? null;

            return ['id' => $user->getId(), 'displayName' => $user->getDisplayName(), 'email' => $user->getEmail(), 'profileImageUrl' => $user->getProfileImagePath() === null ? null : $origin.$user->getProfileImagePath(), 'points' => $accountId === null ? 0 : ($balances[$accountId] ?? 0)];
        }, $memberships), 'page' => $page, 'total' => $total, 'totalPages' => (int) ceil($total / $pageSize)]);
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
