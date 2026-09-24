<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class AuthenticationResponseFactory
{
    public function __construct(
        private readonly JWTTokenManagerInterface $tokens,
        private readonly RefreshTokenGeneratorInterface $refreshTokens,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
    )
    {
    }

    public function create(User $user, Request $request, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return $this->createResponse($user, $request, 'refreshToken', '/api/v1/auth', $status);
    }

    public function createAdmin(User $user, Request $request, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        $membership = $this->memberships->findForUserAndTenant($user, $this->activeTenant->get());
        $response = $this->createResponse($user, $request, 'adminRefreshToken', '/api/v1/admin/auth', $status);
        $data = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $data['permissions'] = $membership === null || in_array('ROLE_TENANT_ADMIN', $membership->getRoles(), true)
            ? AdminAreaPermissions::AREAS
            : ($membership->getPermissions() ?? AdminAreaPermissions::AREAS);
        $data['roles'] = $membership?->getRoles() ?? [];
        $response->setData($data);
        return $response;
    }

    private function createResponse(User $user, Request $request, string $cookieName, string $cookiePath, int $status): JsonResponse
    {
        $data = [
            'accessToken' => $this->tokens->create($user),
            'tokenType' => 'Bearer',
            'expiresIn' => 900,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'displayName' => $user->getDisplayName(),
            ],
        ];
        $refreshToken = $this->refreshTokens->createForUserWithTtl($user, 60 * 60 * 24 * 30);
        $this->refreshTokenManager->save($refreshToken);
        $response = new JsonResponse($data, $status);
        $response->headers->setCookie(Cookie::create($cookieName, (string) $refreshToken, $refreshToken->getValid(), $cookiePath, null, !$this->isLocalHost($request->getHost()), true, false, Cookie::SAMESITE_LAX));
        return $response;
    }

    private function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1'], true);
    }
}
