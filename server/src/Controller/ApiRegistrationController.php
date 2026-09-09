<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActiveTenantProvider;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
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
        EmailVerificationService $emailVerification,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->validationError();
        }

        $email = $payload['email'] ?? null;
        $displayName = $payload['displayName'] ?? null;
        $password = $payload['password'] ?? null;

        if (!is_string($email) || !is_string($displayName) || !is_string($password)) {
            return $this->validationError();
        }

        $email = mb_strtolower(trim($email));
        $username = $email;
        $displayName = trim($displayName);

        if (
            false === filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($email) > 100
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
        $user->setActive(false);
        $tenant = $activeTenant->get();
        $entityManager->persist($user);
        try {
            $emailVerification->send($user, $tenant);
        } catch (TransportExceptionInterface|\RuntimeException) {
            return new JsonResponse(['message' => 'Die Bestätigungs-E-Mail konnte derzeit nicht versendet werden. Bitte versuchen Sie es später erneut.'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse(null, JsonResponse::HTTP_ACCEPTED);
    }

    private function validationError(): JsonResponse
    {
        return new JsonResponse(['message' => 'The submitted registration data is invalid.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
