<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\TenantMembershipRepository;
use App\Repository\UserRepository;
use App\Service\ActiveTenantProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiPasswordResetController
{
    public function __construct(
        private readonly string $clientUrl,
        private readonly string $mailFrom,
    ) {
    }

    #[Route('/api/v1/auth/password-reset/request', name: 'api_v1_auth_password_reset_request', methods: ['POST'])]
    public function requestReset(
        Request $request,
        UserRepository $users,
        TenantMembershipRepository $memberships,
        ActiveTenantProvider $activeTenant,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
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

        $user = $users->findOneByEmail($email);
        if (null === $user || !$user->isActive() || !$memberships->hasActiveMembershipFor($user, $activeTenant->get())) {
            return $this->acceptedResponse();
        }

        $rawToken = bin2hex(random_bytes(32));
        $entityManager->persist(new PasswordResetToken(
            $user,
            hash('sha256', $rawToken),
            new \DateTimeImmutable('+60 minutes'),
        ));
        $entityManager->flush();

        $resetUrl = rtrim($this->clientUrl, '/') . '/passwort-zuruecksetzen?token=' . rawurlencode($rawToken);
        $mailer->send(
            (new Email())
                ->from($this->mailFrom)
                ->to($user->getEmail())
                ->subject('Passwort für Aesculapp zurücksetzen')
                ->text("Sie haben angefordert, Ihr Passwort zurückzusetzen.\n\nÖffnen Sie innerhalb von 60 Minuten diesen Link:\n{$resetUrl}\n\nWenn Sie dies nicht angefordert haben, können Sie diese E-Mail ignorieren."),
        );

        return $this->acceptedResponse();
    }

    #[Route('/api/v1/auth/password-reset/confirm', name: 'api_v1_auth_password_reset_confirm', methods: ['POST'])]
    public function confirmReset(
        Request $request,
        PasswordResetTokenRepository $resetTokens,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            return $this->invalidTokenResponse();
        }

        $token = $payload['token'] ?? null;
        $password = $payload['password'] ?? null;
        if (!is_string($token) || !is_string($password) || mb_strlen($password) < 10) {
            return $this->invalidTokenResponse();
        }

        $resetToken = $resetTokens->findUsableByTokenHash(hash('sha256', $token));
        if (null === $resetToken) {
            return $this->invalidTokenResponse();
        }

        $user = $resetToken->getUser();
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $resetToken->markUsed();
        $resetTokens->invalidateForUser($user);
        $entityManager->flush();

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function acceptedResponse(): JsonResponse
    {
        return new JsonResponse(null, JsonResponse::HTTP_ACCEPTED);
    }

    private function invalidTokenResponse(): JsonResponse
    {
        return new JsonResponse(['message' => 'The password reset token is invalid or expired.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
