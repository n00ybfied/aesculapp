<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tenant;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Email;

final class ReferralSuccessNotifier
{
    public function __construct(
        private readonly TenantMailer $mailer,
        private readonly ChatPushService $push,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.auth.mail_from%')] private readonly string $mailFrom,
        #[Autowire('%app.auth.client_url%')] private readonly string $clientUrl,
    ) {
    }

    public function notify(Tenant $tenant, User $inviter, int $points, int $referralId): void
    {
        $actionUrl = rtrim($this->clientUrl, '/').'/freunde-einladen';
        $emailRewardLine = $points > 0
            ? sprintf('Für diese Einladung wurden Ihnen %d Punkte gutgeschrieben.', $points)
            : 'Für diese Einladung wurden keine Punkte gutgeschrieben.';
        $pushRewardLine = $points > 0
            ? sprintf('%d Punkte wurden gutgeschrieben.', $points)
            : 'Es wurden keine Punkte gutgeschrieben.';

        try {
            $this->mailer->send($tenant, (new Email())
                ->from($this->mailFrom)
                ->to($inviter->getEmail())
                ->subject('Ihre Einladung war erfolgreich')
                ->text(sprintf(
                    "Guten Tag %s,\n\neine Person hat sich über Ihren Einladungslink registriert und ihre E-Mail-Adresse bestätigt.\n\n%s\n\nIhre Einladungen finden Sie hier:\n%s\n",
                    $inviter->getDisplayName(), $emailRewardLine, $actionUrl,
                )), 'referral_success', [
                    'inviter_name' => $inviter->getDisplayName(),
                    'reward_line' => $emailRewardLine,
                    'action_url' => $actionUrl,
                ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('referral.success_email.failed', [
                'referralId' => $referralId,
                'errorClass' => $exception::class,
            ]);
        }

        $this->push->scheduleReferralSuccess($inviter, $tenant, $pushRewardLine, $referralId);
    }
}
