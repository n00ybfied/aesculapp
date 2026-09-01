<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
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
