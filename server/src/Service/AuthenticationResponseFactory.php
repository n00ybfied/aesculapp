<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
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
    )
    {
    }

    public function create(User $user, Request $request, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        $data = [
            'accessToken' => $this->tokens->create($user),
            'tokenType' => 'Bearer',
            'expiresIn' => 900,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'displayName' => $user->getDisplayName(),
            ],
        ];
        $refreshToken = $this->refreshTokens->createForUserWithTtl($user, 60 * 60 * 24 * 30);
        $this->refreshTokenManager->save($refreshToken);
        $response = new JsonResponse($data, $status);
        $response->headers->setCookie(Cookie::create('refreshToken', (string) $refreshToken, $refreshToken->getValid(), '/api/v1/auth', null, !$this->isLocalHost($request->getHost()), true, false, Cookie::SAMESITE_LAX));
        return $response;
    }

    private function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1'], true);
    }
}
