<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{Appointment, ChatMessage, Tenant, User, WebPushSubscription};
use App\Repository\TenantMembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\{Subscription, WebPush};
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ChatPushService
{
    /** @var list<int> */
    private array $scheduled = [];

    /** @var list<array{user: User, tenant: Tenant, title: string, body: string, url: string, tag: string, kind: string}> */
    private array $scheduledFamilyNotifications = [];

    /** @var list<int> */
    private array $scheduledAppointmentBookings = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChatCipher $cipher,
        private readonly LoggerInterface $logger,
        private readonly TenantMembershipRepository $memberships,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly ?NotificationTemplates $notificationTemplates = null,
    ) {
    }

    public function config(): ?array
    {
        $path = $_ENV['WEB_PUSH_KEY_FILE'] ?? $this->projectDir.'/var/private/web-push.json';
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        try {
            $contents = file_get_contents($path);
            $keys = is_string($contents) ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR) : null;
            $publicKey = is_array($keys) ? $keys['publicKey'] ?? null : null;
            $privateKey = is_array($keys) ? $keys['privateKey'] ?? null : null;

            if (!is_string($publicKey) || $publicKey === '' || !is_string($privateKey) || $privateKey === '') {
                return null;
            }

            return [
                'subject' => $_ENV['WEB_PUSH_VAPID_SUBJECT'] ?? 'https://aesculapp.floatbox.at',
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('push.config.invalid', ['errorClass' => $exception::class]);
            return null;
        }
    }

    public function subscribe(User $user, Tenant $tenant, array $data): void
    {
        $endpoint = $data['endpoint'] ?? null;
        $keys = $data['keys'] ?? null;
        if (!is_string($endpoint) || strlen($endpoint) > 2048 || !is_array($keys)) throw new HttpException(422);
        $url = parse_url($endpoint);
        $host = strtolower($url['host'] ?? '');
        $allowed = $host === 'fcm.googleapis.com' || $host === 'web.push.apple.com' || str_ends_with($host, '.push.services.mozilla.com') || str_ends_with($host, '.notify.windows.com');
        if (!$allowed || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || isset($url['port']) && $url['port'] !== 443) throw new HttpException(422);
        foreach (['p256dh' => 65, 'auth' => 16] as $key => $length) {
            $value = $keys[$key] ?? null;
            if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]+={0,2}$/D', $value) || strlen(base64_decode(strtr($value, '-_', '+/'), true) ?: '') !== $length) throw new HttpException(422);
        }
        $hash = hash('sha256', $endpoint);
        $repo = $this->em->getRepository(WebPushSubscription::class);
        $sub = $repo->findOneBy(['endpointHash' => $hash]);
        if (!$sub && $repo->count(['user' => $user, 'tenant' => $tenant]) >= 5) throw new HttpException(422, 'Maximal fünf Geräte.');
        $encrypted = $this->cipher->encrypt(json_encode(['endpoint' => $endpoint, 'keys' => $keys], JSON_THROW_ON_ERROR), 'push:'.$hash);
        if (!$sub) {
            $sub = new WebPushSubscription($tenant, $user, $hash, $encrypted);
            $this->em->persist($sub);
        } else {
            $sub->user = $user;
            $sub->tenant = $tenant;
            $sub->encryptedSubscription = $encrypted;
        }
        $this->em->flush();
    }

    public function remove(User $user, Tenant $tenant, string $endpoint): void
    {
        $sub = $this->em->getRepository(WebPushSubscription::class)->findOneBy(['endpointHash' => hash('sha256', $endpoint), 'user' => $user, 'tenant' => $tenant]);
        if ($sub) {
            $this->em->remove($sub);
            $this->em->flush();
        }
    }

    public function schedule(int $id): void
    {
        $this->scheduled[] = $id;
    }

    public function scheduleFamilyNotification(User $user, Tenant $tenant, string $title, string $body, string $kind = 'family_invitation'): void
    {
        $this->scheduledFamilyNotifications[] = ['user' => $user, 'tenant' => $tenant, 'title' => $title, 'body' => $body, 'url' => '/familie', 'tag' => 'aesculapp-family-access', 'kind' => $kind];
    }

    public function scheduleAppointmentBooking(int $appointmentId): void
    {
        $this->scheduledAppointmentBookings[] = $appointmentId;
    }

    public function sendAppointmentReminder(User $user, Tenant $tenant, string $body, string $appointmentTime): bool
    {
        if (!$this->memberships->findForUserAndTenant($user, $tenant)?->isAppointmentPushEnabled()) {
            return true;
        }

        $config = $this->config();
        if ($config === null) {
            return false;
        }

        try {
            $this->deliverToSubscriptions($user, $tenant, 'Terminerinnerung', $body, '/termine/meine', 'aesculapp-appointment-reminder', $config, null, 'appointment', ['appointment_time' => $appointmentTime]);
            return true;
        } catch (\Throwable $exception) {
            $this->logger->warning('appointment.push.failed', ['errorClass' => $exception::class]);
            return false;
        }
    }

    /** @return array{subscriptions: int, delivered: int, failed: int, configured: bool} */
    public function sendTestNotification(User $user, Tenant $tenant): array
    {
        $config = $this->config();
        if ($config === null) {
            return ['subscriptions' => 0, 'delivered' => 0, 'failed' => 0, 'configured' => false];
        }

        return [
            ...$this->deliverToSubscriptions(
                $user,
                $tenant,
                'AesculApp-Test',
                'Push-Benachrichtigungen funktionieren auf diesem Gerät.',
                '/profil',
                'aesculapp-push-test',
                $config,
                null,
                'test',
            ),
            'configured' => true,
        ];
    }

    public function flushScheduled(): void
    {
        foreach ($this->scheduled as $id) $this->deliver($id);
        $this->scheduled = [];
        foreach ($this->scheduledFamilyNotifications as $notification) $this->deliverFamilyNotification(...$notification);
        $this->scheduledFamilyNotifications = [];
        foreach ($this->scheduledAppointmentBookings as $id) $this->deliverAppointmentBooking($id);
        $this->scheduledAppointmentBookings = [];
    }

    private function deliverAppointmentBooking(int $appointmentId): void
    {
        try {
            $appointment = $this->em->getRepository(Appointment::class)->find($appointmentId);
            if (!$appointment instanceof Appointment || $appointment->getStatus() !== 'reserved' || $appointment->getCustomer() === null) {
                return;
            }
            $user = $appointment->getCustomer();
            $tenant = $appointment->getTenant();
            if (!$this->memberships->findForUserAndTenant($user, $tenant)?->isAppointmentPushEnabled()) {
                return;
            }
            $config = $this->config();
            if ($config === null) {
                return;
            }
            $this->deliverToSubscriptions(
                $user,
                $tenant,
                'Neuer Termin',
                sprintf('Ihre Apotheke hat für Sie am %s um %s Uhr einen Termin eingetragen.', $appointment->getStartsAt()->format('d.m.Y'), $appointment->getStartsAt()->format('H:i')),
                '/termine/meine',
                'aesculapp-appointment-booking',
                $config,
                null,
                'appointment_booking',
                [
                    'appointment_date' => $appointment->getStartsAt()->format('d.m.Y'),
                    'appointment_time' => $appointment->getStartsAt()->format('H:i'),
                ],
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('appointment.booking_push.failed', ['appointmentId' => $appointmentId, 'errorClass' => $exception::class]);
        }
    }

    public function deliver(int $id): void
    {
        try {
            $config = $this->config();
            if (!$config) return;
            $connection = $this->em->getConnection();
            if (!$connection->executeStatement('UPDATE chat_message SET push_pending = 0 WHERE id = ? AND push_pending = 1', [$id])) return;
            $message = $this->em->getRepository(ChatMessage::class)->find($id);
            if (!$message) return;
            $this->em->refresh($message);
            if ($message->customerReadAt !== null) return;
            $membership = $this->memberships->findForUserAndTenant($message->conversation->customer, $message->conversation->tenant);
            if (!$membership?->isChatPushEnabled()) return;
            $this->deliverToSubscriptions($message->conversation->customer, $message->conversation->tenant, 'Neue Antwort', 'Sie haben eine neue Antwort von Ihrer Apotheke.', '/chat', 'apotheke-chat-reply', $config, $id);
        } catch (\Throwable $e) {
            try {
                $this->em->getConnection()->executeStatement('UPDATE chat_message SET push_pending = 1 WHERE id = ?', [$id]);
            } catch (\Throwable) {
            }
            $this->logger->warning('chat.push.failed', ['messageId' => $id, 'errorClass' => $e::class]);
        }
    }

    private function deliverFamilyNotification(User $user, Tenant $tenant, string $title, string $body, string $url, string $tag, string $kind): void
    {
        try {
            if (!$this->memberships->findForUserAndTenant($user, $tenant)?->isFamilyPushEnabled()) return;
            $config = $this->config();
            if (!$config) return;
            $this->deliverToSubscriptions($user, $tenant, $title, $body, $url, $tag, $config, null, $kind);
        } catch (\Throwable $e) {
            $this->logger->warning('family.push.failed', ['errorClass' => $e::class]);
        }
    }

    /** @return array{subscriptions: int, delivered: int, failed: int} */
    /** @param array<string, string> $templateValues */
    private function deliverToSubscriptions(User $user, Tenant $tenant, string $title, string $body, string $url, string $tag, array $config, ?int $messageId = null, string $kind = 'family', array $templateValues = []): array
    {
        if ($this->notificationTemplates !== null) {
            $key = $messageId !== null ? 'push_chat_reply' : 'push_'.match ($kind) {
                'appointment' => 'appointment_reminder',
                'test' => 'test',
                default => $kind,
            };
            try {
                $rendered = $this->notificationTemplates->render($tenant, $key, $title, $body, $templateValues);
                if (preg_match('/{{[^{}]+}}/', $rendered['title'].$rendered['body'])) {
                    throw new \RuntimeException('Unresolved notification insert tag.');
                }
                $title = $rendered['title'];
                $body = $rendered['body'];
            } catch (\Throwable $exception) {
                $this->logger->warning('push.template.failed', ['kind' => $kind, 'errorClass' => $exception::class]);
            }
        }
        $subs = $this->em->getRepository(WebPushSubscription::class)->findBy(['user' => $user, 'tenant' => $tenant]);
        $push = new WebPush(
            ['VAPID' => $config],
            ['TTL' => 3600],
            new \GuzzleHttp\Client(['timeout' => 5, 'connect_timeout' => 3, 'allow_redirects' => false]),
            logger: $this->logger,
        );
        $retry = false;
        $delivered = 0;
        $failed = 0;
        foreach ($subs as $sub) {
            try {
                $data = json_decode($this->cipher->decrypt($sub->encryptedSubscription, 'push:'.$sub->endpointHash), true, 512, JSON_THROW_ON_ERROR);
                $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag], JSON_THROW_ON_ERROR);
                $report = $push->sendOneNotification(Subscription::create($data), $payload);
                if ($report->isSubscriptionExpired()) {
                    $this->em->remove($sub);
                    ++$failed;
                }
                elseif ($report->isSuccess()) {
                    ++$delivered;
                }
                elseif (!$report->isSuccess()) {
                    $retry = true;
                    ++$failed;
                    $this->logger->warning('push.delivery_failed', ['kind' => $messageId === null ? $kind : 'chat', 'statusCode' => $report->getResponse()?->getStatusCode()]);
                }
            } catch (\Throwable $e) {
                $retry = true;
                ++$failed;
                $this->logger->warning('push.delivery_failed', ['kind' => $messageId === null ? $kind : 'chat', 'errorClass' => $e::class]);
            }
        }
        $this->em->flush();
        if ($retry && $messageId !== null) $this->em->getConnection()->executeStatement('UPDATE chat_message SET push_pending = 1 WHERE id = ?', [$messageId]);
        return ['subscriptions' => count($subs), 'delivered' => $delivered, 'failed' => $failed];
    }
}
