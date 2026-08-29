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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiAdminLoginController
{
    /** @var list<string> */
    private const ADMIN_ROLES = ['ROLE_TENANT_STAFF', 'ROLE_TENANT_ADMIN'];

    #[Route('/api/v1/admin/auth/login', name: 'api_v1_admin_auth_login', methods: ['POST'])]
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
        $membership = null === $user ? null : $memberships->findForUserAndTenant($user, $activeTenant->get());

        if (
            null === $user
            || !$user->isActive()
            || !$passwordHasher->isPasswordValid($user, $password)
            || null === $membership
            || [] === array_intersect(self::ADMIN_ROLES, $membership->getRoles())
        ) {
            return $this->invalidCredentialsResponse();
        }

        return $responses->create($user);
    }

    private function invalidCredentialsResponse(): JsonResponse
    {
        return new JsonResponse(['message' => 'Invalid credentials.'], JsonResponse::HTTP_UNAUTHORIZED);
    }
}
