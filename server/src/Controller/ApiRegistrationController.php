<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TenantMembership;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActiveTenantProvider;
use App\Service\AuthenticationResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiRegistrationController
{
    #[Route('/api/v1/auth/register', name: 'api_v1_auth_register', methods: ['POST'])]
    public function __invoke(
        Request $request,
        UserRepository $users,
        ActiveTenantProvider $activeTenant,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        AuthenticationResponseFactory $responses,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->validationError();
        }

        $username = $payload['username'] ?? null;
        $email = $payload['email'] ?? null;
        $displayName = $payload['displayName'] ?? null;
        $password = $payload['password'] ?? null;

        if (!is_string($username) || !is_string($email) || !is_string($displayName) || !is_string($password)) {
            return $this->validationError();
        }

        $username = mb_strtolower(trim($username));
        $email = mb_strtolower(trim($email));
        $displayName = trim($displayName);

        if (
            !preg_match('/^[a-z0-9][a-z0-9._-]{2,99}$/', $username)
            || false === filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($displayName) < 2
            || mb_strlen($displayName) > 160
            || mb_strlen($password) < 10
        ) {
            return $this->validationError();
        }

        if (null !== $users->findOneByUsername($username) || null !== $users->findOneByEmail($email)) {
            return new JsonResponse(['message' => 'An account with these details already exists.'], JsonResponse::HTTP_CONFLICT);
        }

        $user = new User($username, $email, $displayName);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $entityManager->persist($user);
        $entityManager->flush();

        $entityManager->persist(new TenantMembership($activeTenant->get(), $user));
        $entityManager->flush();

        return $responses->create($user, JsonResponse::HTTP_CREATED);
    }

    private function validationError(): JsonResponse
    {
        return new JsonResponse(['message' => 'The submitted registration data is invalid.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
