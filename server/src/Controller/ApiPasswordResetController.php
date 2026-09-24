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
use App\Service\TenantMailer;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiPasswordResetController
{
    public function __construct(
        private readonly string $clientUrl,
        private readonly string $adminUrl,
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
        TenantMailer $mailer,
        PasswordResetTokenRepository $resetTokens,
    ): JsonResponse {
        return $this->sendResetLink($request, $users, $memberships, $activeTenant, $entityManager, $mailer, $resetTokens, false);
    }

    #[Route('/api/v1/admin/auth/password-reset/request', name: 'api_v1_admin_password_reset_request', methods: ['POST'])]
    public function requestAdminReset(
        Request $request,
        UserRepository $users,
        TenantMembershipRepository $memberships,
        ActiveTenantProvider $activeTenant,
        EntityManagerInterface $entityManager,
        TenantMailer $mailer,
        PasswordResetTokenRepository $resetTokens,
    ): JsonResponse {
        return $this->sendResetLink($request, $users, $memberships, $activeTenant, $entityManager, $mailer, $resetTokens, true);
    }

    private function sendResetLink(
        Request $request,
        UserRepository $users,
        TenantMembershipRepository $memberships,
        ActiveTenantProvider $activeTenant,
        EntityManagerInterface $entityManager,
        TenantMailer $mailer,
        PasswordResetTokenRepository $resetTokens,
        bool $admin,
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
        $membership = $user === null ? null : $memberships->findForUserAndTenant($user, $activeTenant->get());
        if (null === $user || !$user->isActive() || $membership === null || ($admin && !$this->hasAdminRole($membership->getRoles()))) {
            return $this->acceptedResponse();
        }
        $mostRecent = $resetTokens->findMostRecentFor($user);
        if ($mostRecent !== null && $mostRecent->getRequestedAt() > new \DateTimeImmutable('-1 minute')) {
            return $this->acceptedResponse();
        }

        $rawToken = bin2hex(random_bytes(32));
        $entityManager->persist(new PasswordResetToken(
            $user,
            hash('sha256', $rawToken),
            new \DateTimeImmutable('+60 minutes'),
        ));
        $resetUrl = rtrim($admin ? $this->adminUrl : $this->clientUrl, '/') . '/passwort-zuruecksetzen?token=' . rawurlencode($rawToken);
        $subject = $admin ? 'Passwort für das Aesculapp Apothekenportal zurücksetzen' : 'Passwort für Aesculapp zurücksetzen';
        try {
            $mailer->send(
                $activeTenant->get(),
                (new Email())
                    ->from($this->mailFrom)
                    ->to($user->getEmail())
                    ->subject($subject)
                    ->text("Sie haben angefordert, Ihr Passwort zurückzusetzen.\n\nÖffnen Sie innerhalb von 60 Minuten diesen Link:\n{$resetUrl}\n\nWenn Sie dies nicht angefordert haben, können Sie diese E-Mail ignorieren."),
                'password_reset',
            );
            $entityManager->flush();
        } catch (\Throwable) {
            // Die einheitliche Antwort verhindert eine Preisgabe vorhandener Konten oder Mail-Fehler.
        }

        return $this->acceptedResponse();
    }

    #[Route('/api/v1/auth/password-reset/confirm', name: 'api_v1_auth_password_reset_confirm', methods: ['POST'])]
    public function confirmReset(
        Request $request,
        PasswordResetTokenRepository $resetTokens,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        RevokeRefreshTokenManagerInterface $refreshTokens,
    ): JsonResponse {
        return $this->confirmPassword($request, $resetTokens, $entityManager, $passwordHasher, $refreshTokens, null, null);
    }

    #[Route('/api/v1/admin/auth/password-reset/confirm', name: 'api_v1_admin_password_reset_confirm', methods: ['POST'])]
    public function confirmAdminReset(
        Request $request,
        PasswordResetTokenRepository $resetTokens,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        RevokeRefreshTokenManagerInterface $refreshTokens,
        TenantMembershipRepository $memberships,
        ActiveTenantProvider $activeTenant,
    ): JsonResponse {
        return $this->confirmPassword($request, $resetTokens, $entityManager, $passwordHasher, $refreshTokens, $memberships, $activeTenant);
    }

    private function confirmPassword(
        Request $request,
        PasswordResetTokenRepository $resetTokens,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        RevokeRefreshTokenManagerInterface $refreshTokens,
        ?TenantMembershipRepository $memberships,
        ?ActiveTenantProvider $activeTenant,
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
        if ($memberships !== null && $activeTenant !== null) {
            $membership = $memberships->findForUserAndTenant($user, $activeTenant->get());
            if (!$user->isActive() || $membership === null || !$this->hasAdminRole($membership->getRoles())) {
                return $this->invalidTokenResponse();
            }
        }
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $resetToken->markUsed();
        $resetTokens->invalidateForUser($user);
        $refreshTokens->revokeAllForUser($user);
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

    /** @param list<string> $roles */
    private function hasAdminRole(array $roles): bool
    {
        return in_array('ROLE_TENANT_STAFF', $roles, true) || in_array('ROLE_TENANT_ADMIN', $roles, true);
    }
}
