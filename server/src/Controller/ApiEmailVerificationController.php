<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\TenantMembership;
use App\Repository\EmailVerificationTokenRepository;
use App\Service\PointAccountProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiEmailVerificationController
{
    public function __construct(private readonly PointAccountProvisioner $pointAccounts)
    {
    }

    #[Route('/api/v1/auth/email-verification/confirm', name: 'api_v1_auth_email_verification_confirm', methods: ['POST'])]
    public function confirm(Request $request, EmailVerificationTokenRepository $tokens, EntityManagerInterface $entityManager): JsonResponse
    {
        try { $payload = $request->toArray(); } catch (JsonException) { return $this->invalidResponse(); }
        $rawToken = $payload['token'] ?? null;
        if (!is_string($rawToken) || $rawToken === '') { return $this->invalidResponse(); }
        $token = $tokens->findUsableByTokenHash(hash('sha256', $rawToken));
        if ($token === null) { return $this->invalidResponse(); }

        $user = $token->getUser();
        $tenant = $token->getTenant();
        $user->setActive(true);
        if ($entityManager->getRepository(TenantMembership::class)->findOneBy(['tenant' => $tenant, 'user' => $user]) === null) {
            $entityManager->persist(new TenantMembership($tenant, $user));
        }
        $this->pointAccounts->getOrCreate($tenant, $user);
        $token->markUsed();
        $entityManager->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function invalidResponse(): JsonResponse { return new JsonResponse(['message' => 'Der Bestätigungslink ist ungültig oder abgelaufen.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY); }
}
