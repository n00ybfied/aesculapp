<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailVerificationToken;
use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\EmailVerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;

final class EmailVerificationService
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly EmailVerificationTokenRepository $tokens, private readonly TenantMailer $mailer, private readonly string $clientUrl, private readonly string $mailFrom) {}

    public function send(User $user, Tenant $tenant): void
    {
        $this->sendNewToken($user, $tenant, false);
    }

    public function resendIfAllowed(User $user, Tenant $tenant): bool
    {
        $mostRecent = $this->tokens->findMostRecentFor($user, $tenant);
        if ($mostRecent !== null && $mostRecent->getRequestedAt() > new \DateTimeImmutable('-1 minute')) {
            return false;
        }

        $this->sendNewToken($user, $tenant, true);

        return true;
    }

    private function sendNewToken(User $user, Tenant $tenant, bool $invalidatePreviousTokens): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $this->entityManager->persist(new EmailVerificationToken($user, $tenant, hash('sha256', $rawToken), new \DateTimeImmutable('+24 hours')));
        $verificationUrl = rtrim($this->clientUrl, '/') . '/e-mail-bestaetigen?token=' . rawurlencode($rawToken);
        $this->mailer->send($tenant, (new Email())->from($this->mailFrom)->to($user->getEmail())->subject('E-Mail-Adresse für Aesculapp bestätigen')->text("Bitte bestätigen Sie Ihre E-Mail-Adresse innerhalb von 24 Stunden:\n{$verificationUrl}\n\nErst danach ist Ihr Konto aktiv."), 'email_verification');
        if ($invalidatePreviousTokens) {
            $this->tokens->invalidatePendingFor($user, $tenant);
        }
        $this->entityManager->flush();
    }
}
