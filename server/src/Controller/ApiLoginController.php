<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TenantMembershipRepository;
use App\Repository\UserRepository;
use App\Service\ActiveTenantProvider;
use App\Service\AuthenticationResponseFactory;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ApiLoginController
{
    #[Route('/api/v1/auth/login', name: 'api_v1_auth_login', methods: ['POST'])]
    public function __invoke(
        Request $request,
        UserRepository $users,
        TenantMembershipRepository $memberships,
        ActiveTenantProvider $activeTenant,
        UserPasswordHasherInterface $passwordHasher,
        AuthenticationResponseFactory $responses,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->invalidCredentialsResponse();
        }

        $username = $payload['username'] ?? null;
        $password = $payload['password'] ?? null;
        if (!is_string($username) || !is_string($password)) {
            return $this->invalidCredentialsResponse();
        }

        $user = $users->findOneByUsername($username);
        if (
            null === $user
            || !$user->isActive()
            || !$passwordHasher->isPasswordValid($user, $password)
            || !$memberships->hasActiveMembershipFor($user, $activeTenant->get())
        ) {
            return $this->invalidCredentialsResponse();
        }

        return $responses->create($user);
    }

    private function invalidCredentialsResponse(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Invalid credentials.',
        ], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
