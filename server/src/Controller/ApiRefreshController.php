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

final class ApiRefreshController
{
    public function __construct(
        private readonly RefreshTokenManagerInterface $tokens,
        private readonly UserRepository $users,
        private readonly TenantMembershipRepository $memberships,
        private readonly ActiveTenantProvider $tenant,
        private readonly AuthenticationResponseFactory $responses,
    ) {
    }

    #[Route('/api/v1/auth/refresh', name: 'api_v1_auth_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $value = $request->cookies->get('refreshToken');
        $refresh = is_string($value) ? $this->tokens->get($value) : null;
        if ($refresh === null || !$refresh->isValid()) {
            return $this->invalid();
        }
        $user = $this->users->findOneByUsername((string) $refresh->getUsername());
        if (!$user instanceof User || !$user->isActive() || !$this->memberships->hasActiveMembershipFor($user, $this->tenant->get())) {
            return $this->invalid();
        }
        $this->tokens->delete($refresh);
        return $this->responses->create($user, $request);
    }

    #[Route('/api/v1/auth/logout', name: 'api_v1_auth_logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $value = $request->cookies->get('refreshToken');
        $refresh = is_string($value) ? $this->tokens->get($value) : null;
        if ($refresh !== null) {
            $this->tokens->delete($refresh);
        }
        $response = new Response(status: Response::HTTP_NO_CONTENT);
        $response->headers->clearCookie('refreshToken', '/api/v1/auth');
        return $response;
    }

    private function invalid(): JsonResponse
    {
        $response = new JsonResponse(['message' => 'Invalid refresh token.'], Response::HTTP_UNAUTHORIZED);
        $response->headers->clearCookie('refreshToken', '/api/v1/auth');
        return $response;
    }
}
