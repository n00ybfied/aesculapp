<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\EmailVerificationTokenRepository;
use App\Service\ActiveTenantProvider;
use App\Service\EmailVerificationService;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiEmailVerificationResendController
{
    #[Route('/api/v1/auth/email-verification/resend', name: 'api_v1_auth_email_verification_resend', methods: ['POST'])]
    public function __invoke(
        Request $request,
        UserRepository $users,
        EmailVerificationTokenRepository $tokens,
        ActiveTenantProvider $activeTenant,
        EmailVerificationService $emailVerification,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->acceptedResponse();
        }

        $email = $payload['email'] ?? null;
        if (!is_string($email) || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->acceptedResponse();
        }

        $tenant = $activeTenant->get();
        $user = $users->findOneByEmail($email);
        if ($user === null || $user->isActive() || $tokens->findMostRecentFor($user, $tenant) === null) {
            return $this->acceptedResponse();
        }

        try {
            $emailVerification->resendIfAllowed($user, $tenant);
        } catch (\Throwable) {
            // The response must not disclose account state or mail transport failures.
        }

        return $this->acceptedResponse();
    }

    private function acceptedResponse(): JsonResponse
    {
        return new JsonResponse(null, JsonResponse::HTTP_ACCEPTED);
    }
}
