<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class AuthenticationResponseFactory
{
    public function __construct(private readonly JWTTokenManagerInterface $tokens)
    {
    }

    public function create(User $user, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            'accessToken' => $this->tokens->create($user),
            'tokenType' => 'Bearer',
            'expiresIn' => 900,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'displayName' => $user->getDisplayName(),
            ],
        ], $status);
    }
}
