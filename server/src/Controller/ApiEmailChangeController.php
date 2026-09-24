<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EmailChangeToken;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Repository\TenantMembershipRepository;
use App\Service\ActiveTenantProvider;
use App\Service\TenantMailer;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RevokeRefreshTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ApiEmailChangeController
{
    public function __construct(
        private readonly Security $security,
        private readonly ActiveTenantProvider $activeTenant,
        private readonly TenantMembershipRepository $memberships,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TenantMailer $mailer,
        private readonly RevokeRefreshTokenManagerInterface $refreshTokens,
        #[Autowire('%app.auth.client_url%')]
        private readonly string $clientUrl,
        #[Autowire('%app.auth.mail_from%')]
        private readonly string $mailFrom,
    ) {
    }

    #[Route('/api/v1/profile/email-change/request', methods: ['POST'])]
    public function requestChange(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->memberships->hasCustomerMembershipFor($user, $this->activeTenant->get())) {
            return new JsonResponse(['message' => 'Forbidden.'], JsonResponse::HTTP_FORBIDDEN);
        }
        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->invalidRequest();
        }
        $newEmail = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        if (!is_string($newEmail) || !is_string($password)) return $this->invalidRequest();
        $newEmail = mb_strtolower(trim($newEmail));
        if (mb_strlen($newEmail) > 180 || filter_var($newEmail, FILTER_VALIDATE_EMAIL) === false || $newEmail === $user->getEmail()) {
            return $this->invalidRequest();
        }
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['message' => 'Das aktuelle Passwort ist nicht korrekt.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($this->entityManager->getRepository(User::class)->findOneBy(['email' => $newEmail]) !== null) {
            return new JsonResponse(['message' => 'Diese E-Mail-Adresse wird bereits verwendet.'], JsonResponse::HTTP_CONFLICT);
        }
        $tokens = $this->entityManager->getRepository(EmailChangeToken::class);
        $recent = $tokens->findOneBy(['user' => $user], ['requestedAt' => 'DESC']);
        if ($recent instanceof EmailChangeToken && $recent->getRequestedAt() > new \DateTimeImmutable('-1 minute')) {
            return new JsonResponse(['message' => 'Bitte warten Sie eine Minute, bevor Sie einen neuen Link anfordern.'], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $rawToken = bin2hex(random_bytes(32));
        $token = new EmailChangeToken($user, $this->activeTenant->get(), $newEmail, hash('sha256', $rawToken));
        $url = rtrim($this->clientUrl, '/').'/e-mail-aendern?token='.rawurlencode($rawToken);
        try {
            $this->mailer->send(
                $this->activeTenant->get(),
                (new Email())->from($this->mailFrom)->to($newEmail)
                    ->subject('Neue E-Mail-Adresse für Aesculapp bestätigen')
                    ->text("Sie haben eine Änderung Ihrer E-Mail-Adresse angefordert.\n\nBestätigen Sie die neue Adresse innerhalb von 24 Stunden:\n{$url}\n\nWenn Sie dies nicht angefordert haben, ignorieren Sie diese E-Mail."),
                'email_change',
                ['action_url' => $url],
            );
            $this->entityManager->persist($token);
            $this->entityManager->flush();
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Die Bestätigungs-E-Mail konnte nicht versendet werden.'], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }
        return new JsonResponse(['message' => 'Wir haben einen Bestätigungslink an die neue E-Mail-Adresse gesendet.']);
    }

    #[Route('/api/v1/auth/email-change/confirm', methods: ['POST'])]
    public function confirmChange(Request $request): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (JsonException) {
            return $this->invalidToken();
        }
        $rawToken = $data['token'] ?? null;
        if (!is_string($rawToken) || strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) return $this->invalidToken();

        try {
            return $this->entityManager->wrapInTransaction(function () use ($rawToken): JsonResponse {
                $token = $this->entityManager->getRepository(EmailChangeToken::class)->findOneBy(['tokenHash' => hash('sha256', $rawToken)]);
                if (!$token instanceof EmailChangeToken) return $this->invalidToken();
                $this->entityManager->lock($token, LockMode::PESSIMISTIC_WRITE);
                $this->entityManager->refresh($token);
                if (!$token->isUsable() || $token->getTenant()->getId() !== $this->activeTenant->get()->getId()) return $this->invalidToken();
                $user = $token->getUser();
                $this->entityManager->lock($user, LockMode::PESSIMISTIC_WRITE);
                $this->entityManager->refresh($user);
                if (!$this->memberships->hasCustomerMembershipFor($user, $token->getTenant())) {
                    return $this->invalidToken();
                }
                if ($this->entityManager->getRepository(User::class)->findOneBy(['email' => $token->getNewEmail()]) !== null) {
                    return new JsonResponse(['message' => 'Diese E-Mail-Adresse wird bereits verwendet.'], JsonResponse::HTTP_CONFLICT);
                }
                $user->setEmail($token->getNewEmail());
                foreach ($this->entityManager->getRepository(EmailChangeToken::class)->findBy(['user' => $user, 'usedAt' => null]) as $pending) {
                    $pending->markUsed();
                }
                $this->refreshTokens->revokeAllForUser($user);
                $this->entityManager->flush();
                return new JsonResponse(['message' => 'Ihre E-Mail-Adresse wurde geändert. Bitte melden Sie sich neu an.']);
            });
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['message' => 'Diese E-Mail-Adresse wird bereits verwendet.'], JsonResponse::HTTP_CONFLICT);
        }
    }

    private function invalidRequest(): JsonResponse
    {
        return new JsonResponse(['message' => 'Bitte geben Sie eine gültige neue E-Mail-Adresse und Ihr aktuelles Passwort ein.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function invalidToken(): JsonResponse
    {
        return new JsonResponse(['message' => 'Der Bestätigungslink ist ungültig oder abgelaufen.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
