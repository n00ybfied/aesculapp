<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\{NativePushDevice, Tenant, User};
use Doctrine\ORM\EntityManagerInterface;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

final class NativePushService
{
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ChatCipher $cipher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function expectedAndroidAppId(): ?string
    {
        $id = $_ENV['NATIVE_PUSH_ANDROID_APP_ID'] ?? '';
        return is_string($id) && $id !== '' ? $id : null;
    }

    public function configured(): bool
    {
        $path = $_ENV['FCM_SERVICE_ACCOUNT_FILE'] ?? '';
        $caFile = $_ENV['FCM_CA_FILE'] ?? '';
        if ($this->expectedAndroidAppId() === null || !is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            return false;
        }
        if ($caFile !== '' && (!is_string($caFile) || !is_file($caFile) || !is_readable($caFile))) {
            return false;
        }
        $config = json_decode((string) file_get_contents($path), true);
        return is_array($config)
            && is_string($config['project_id'] ?? null)
            && is_string($config['client_email'] ?? null)
            && is_string($config['private_key'] ?? null);
    }

    public function register(User $user, Tenant $tenant, string $token, string $appId): bool
    {
        if ($appId !== $this->expectedAndroidAppId() || !self::validToken($token)) {
            return false;
        }

        $hash = hash('sha256', $token);
        $repository = $this->em->getRepository(NativePushDevice::class);
        $device = $repository->findOneBy(['tokenHash' => $hash]);
        if (($device === null || $device->user !== $user || $device->tenant !== $tenant)
            && $repository->count(['user' => $user, 'tenant' => $tenant]) >= 5) {
            throw new \DomainException('Maximal fünf native Geräte pro Kundenkonto.');
        }
        if ($device === null) {
            $device = new NativePushDevice($tenant, $user, $hash, $this->cipher->encrypt($token, 'native-push:'.$hash), 'android', $appId);
            $this->em->persist($device);
        } else {
            $device->tenant = $tenant;
            $device->user = $user;
            $device->encryptedToken = $this->cipher->encrypt($token, 'native-push:'.$hash);
            $device->appId = $appId;
            $device->updatedAt = new \DateTimeImmutable();
        }
        $this->em->flush();
        return true;
    }

    public function isRegistered(User $user, Tenant $tenant, string $token): bool
    {
        return self::validToken($token) && $this->em->getRepository(NativePushDevice::class)->findOneBy([
            'user' => $user,
            'tenant' => $tenant,
            'tokenHash' => hash('sha256', $token),
        ]) !== null;
    }

    public function removeByToken(string $token): void
    {
        if (!self::validToken($token)) {
            return;
        }
        // Possession of the device token allows removal of a stale registration from
        // another account on a shared handset after an interrupted logout.
        $device = $this->em->getRepository(NativePushDevice::class)->findOneBy([
            'tokenHash' => hash('sha256', $token),
        ]);
        if ($device !== null) {
            $this->em->remove($device);
            $this->em->flush();
        }
    }

    /** @return array{subscriptions: int, delivered: int, failed: int} */
    public function sendToUser(User $user, Tenant $tenant, string $kind): array
    {
        $devices = $this->em->getRepository(NativePushDevice::class)->findBy(['user' => $user, 'tenant' => $tenant, 'platform' => 'android']);
        $result = ['subscriptions' => count($devices), 'delivered' => 0, 'failed' => 0];
        if ($devices === []) {
            return $result;
        }
        if (!$this->configured()) {
            $result['failed'] = count($devices);
            return $result;
        }

        try {
            [$projectId, $accessToken] = $this->authorization();
        } catch (\Throwable $exception) {
            $this->logger->warning('native_push.authorization_failed', ['errorClass' => $exception::class]);
            $result['failed'] = count($devices);
            return $result;
        }

        $client = new Client(['timeout' => 8, 'connect_timeout' => 3, 'allow_redirects' => false, 'verify' => $this->tlsVerify()]);
        foreach ($devices as $device) {
            try {
                $token = $this->cipher->decrypt($device->encryptedToken, 'native-push:'.$device->tokenHash);
                $client->post('https://fcm.googleapis.com/v1/projects/'.rawurlencode($projectId).'/messages:send', [
                    'headers' => ['Authorization' => 'Bearer '.$accessToken],
                    'json' => ['message' => [
                        'token' => $token,
                        // FCM only receives neutral text and a neutral route. Details are fetched after app authentication.
                        'notification' => ['title' => 'Nachricht Ihrer Apotheke', 'body' => 'Öffnen Sie die App für weitere Informationen.'],
                        'data' => ['url' => '/dashboard'],
                        'android' => [
                            'priority' => in_array($kind, ['appointment', 'appointment_booking', 'chat'], true) ? 'HIGH' : 'NORMAL',
                            'notification' => ['channel_id' => 'aesculapp-updates', 'tag' => 'aesculapp-update'],
                        ],
                    ]],
                ]);
                ++$result['delivered'];
            } catch (RequestException $exception) {
                $status = $exception->getResponse()?->getStatusCode();
                if ($status === 404 && $this->isUnregistered($exception)) {
                    $this->em->remove($device);
                } else {
                    ++$result['failed'];
                    $this->logger->warning('native_push.delivery_failed', ['kind' => $kind, 'statusCode' => $status]);
                }
            } catch (\Throwable $exception) {
                ++$result['failed'];
                $this->logger->warning('native_push.delivery_failed', ['kind' => $kind, 'errorClass' => $exception::class]);
            }
        }
        $this->em->flush();
        return $result;
    }

    private function authorization(): array
    {
        $path = (string) ($_ENV['FCM_SERVICE_ACCOUNT_FILE'] ?? '');
        $json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $projectId = $json['project_id'] ?? null;
        if (!is_string($projectId) || !preg_match('/^[a-z][a-z0-9-]{4,63}$/D', $projectId)) {
            throw new \RuntimeException('Invalid Firebase project ID.');
        }
        if ($this->accessToken === null || $this->accessTokenExpiresAt <= time()) {
            $credentials = new ServiceAccountCredentials('https://www.googleapis.com/auth/firebase.messaging', $json);
            $handler = HttpHandlerFactory::build(new Client(['timeout' => 8, 'connect_timeout' => 3, 'verify' => $this->tlsVerify()]));
            $token = $credentials->fetchAuthToken($handler);
            if (!is_string($token['access_token'] ?? null)) {
                throw new \RuntimeException('FCM access token unavailable.');
            }
            $this->accessToken = $token['access_token'];
            $this->accessTokenExpiresAt = time() + max(60, (int) ($token['expires_in'] ?? 3600) - 60);
        }
        return [$projectId, $this->accessToken];
    }

    private function isUnregistered(RequestException $exception): bool
    {
        $body = (string) $exception->getResponse()?->getBody();
        $data = json_decode($body, true);
        foreach ($data['error']['details'] ?? [] as $detail) {
            if (($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }
        return false;
    }

    private function tlsVerify(): bool|string
    {
        $caFile = $_ENV['FCM_CA_FILE'] ?? '';
        return is_string($caFile) && $caFile !== '' ? $caFile : true;
    }

    public static function validToken(string $token): bool
    {
        return (bool) preg_match('/^[!-~]{20,4096}$/D', $token);
    }
}
