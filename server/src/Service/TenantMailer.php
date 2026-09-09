<?php
declare(strict_types=1);
namespace App\Service;
use App\Entity\Tenant;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
final class TenantMailer { public function __construct(private readonly MailerInterface $fallback, private readonly string $appSecret, private readonly string $smtpOverrideDsn = '') {} public function send(Tenant $tenant, Email $email): void { if ($this->smtpOverrideDsn !== '') { Transport::fromDsn($this->smtpOverrideDsn)->send($email); return; } if ($tenant->getSmtpHost() === null || $tenant->getSmtpPort() === null || $tenant->getSmtpPasswordEncrypted() === null) { $this->fallback->send($email); return; } [$cipher,$nonce] = explode(':', $tenant->getSmtpPasswordEncrypted(), 2); $password = sodium_crypto_secretbox_open(base64_decode($cipher), base64_decode($nonce), hash('sha256', $this->appSecret, true)); if ($password === false) { throw new \RuntimeException('SMTP password cannot be decrypted.'); } $transport = new EsmtpTransport($tenant->getSmtpHost(), $tenant->getSmtpPort(), $tenant->getSmtpEncryption() === 'ssl'); $transport->setAutoTls($tenant->getSmtpEncryption() !== 'none')->setUsername($tenant->getSmtpUsername() ?? '')->setPassword($password); $email->from($tenant->getSmtpFrom() ?? ''); $transport->send($email); } }
