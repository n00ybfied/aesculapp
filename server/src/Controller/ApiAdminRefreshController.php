<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Repository\UserRepository;
use App\Service\ActiveTenantProvider;
use App\Service\AuthenticationResponseFactory;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAdminRefreshController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    public function __construct(
        private readonly RefreshTokenManagerInterface $tokens,
        private readonly UserRepository $users,
        private readonly TenantMembershipRepository $memberships,
        private readonly ActiveTenantProvider $tenant,
        private readonly AuthenticationResponseFactory $responses,
    ) {}

    #[Route('/api/v1/admin/auth/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $value = $request->cookies->get('adminRefreshToken');
        $refresh = is_string($value) ? $this->tokens->get($value) : null;
        $user = $refresh === null ? null : $this->users->findOneByUsername((string) $refresh->getUsername());
        $membership = $user instanceof User ? $this->memberships->findForUserAndTenant($user, $this->tenant->get()) : null;

        if ($refresh === null || !$refresh->isValid() || !($user instanceof User) || !$user->isActive() || $membership === null || [] === array_intersect(self::ADMIN_ROLES, $membership->getRoles())) {
            return $this->invalid();
        }

        $this->tokens->delete($refresh);
        return $this->responses->createAdmin($user, $request);
    }

    #[Route('/api/v1/admin/auth/logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $value = $request->cookies->get('adminRefreshToken');
        $refresh = is_string($value) ? $this->tokens->get($value) : null;
        if ($refresh !== null) $this->tokens->delete($refresh);
        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie('adminRefreshToken', '/api/v1/admin/auth');
        return $response;
    }

    private function invalid(): JsonResponse
    {
        $response = new JsonResponse(['message' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        $response->headers->clearCookie('adminRefreshToken', '/api/v1/admin/auth');
        return $response;
    }
}
