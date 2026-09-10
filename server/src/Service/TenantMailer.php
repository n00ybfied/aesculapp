<?php
declare(strict_types=1);
namespace App\Service;

use App\Entity\Tenant;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class TenantMailer
{
    public function __construct(
        private readonly MailerInterface $fallback,
        private readonly string $appSecret,
        #[Autowire(service: 'monolog.logger.email')]
        private readonly LoggerInterface $emailLogger,
        private readonly string $smtpOverrideDsn = '',
    ) {
    }

    public function send(Tenant $tenant, Email $email, string $messageType = 'transactional'): void
    {
        $delivery = $this->smtpOverrideDsn !== '' ? 'environment_override' : 'fallback';
        if ($this->smtpOverrideDsn === '' && $tenant->getSmtpHost() !== null && $tenant->getSmtpPort() !== null && $tenant->getSmtpPasswordEncrypted() !== null) {
            $delivery = 'tenant_smtp';
        }

        $context = [
            'tenantId' => $tenant->getId(),
            'messageType' => $messageType,
            'recipientHashPrefix' => $this->recipientHashPrefix($email),
            'delivery' => $delivery,
        ];
        $this->emailLogger->info('email.send.requested', $context);

        try {
            if ($this->smtpOverrideDsn !== '') {
                Transport::fromDsn($this->smtpOverrideDsn)->send($email);
            } elseif ($delivery === 'fallback') {
                $this->fallback->send($email);
            } else {
                [$cipher, $nonce] = explode(':', $tenant->getSmtpPasswordEncrypted() ?? '', 2);
                $password = sodium_crypto_secretbox_open(base64_decode($cipher), base64_decode($nonce), hash('sha256', $this->appSecret, true));
                if ($password === false) {
                    throw new \RuntimeException('SMTP password cannot be decrypted.');
                }

                $transport = new EsmtpTransport($tenant->getSmtpHost() ?? '', $tenant->getSmtpPort() ?? 0, $tenant->getSmtpEncryption() === 'ssl');
                $transport->setAutoTls($tenant->getSmtpEncryption() !== 'none')->setUsername($tenant->getSmtpUsername() ?? '')->setPassword($password);
                $email->from($tenant->getSmtpFrom() ?? '');
                $transport->send($email);
            }

            $this->emailLogger->info('email.send.accepted_by_transport', $context);
        } catch (\Throwable $exception) {
            $this->emailLogger->error('email.send.failed', $context + [
                'exceptionClass' => $exception::class,
                'exceptionCode' => $exception->getCode(),
                'reason' => $this->sanitizeFailureReason($exception->getMessage()),
            ]);
            throw $exception;
        }
    }

    private function recipientHashPrefix(Email $email): string
    {
        $recipients = array_map(static fn (Address $address): string => mb_strtolower($address->getAddress()), $email->getTo());

        return substr(hash('sha256', implode(',', $recipients)), 0, 12);
    }

    private function sanitizeFailureReason(string $message): string
    {
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}/i', '[redacted-email]', $message) ?? 'Unknown mail transport error.';
        $message = preg_replace('#://[^/\\s:@]+:[^@/\\s]+@#', '://[redacted]@', $message) ?? $message;
        $message = preg_replace('/([?&](?:token|password|secret)=[^&\\s]+)/i', '[redacted-parameter]', $message) ?? $message;

        return mb_substr($message, 0, 500);
    }
}
