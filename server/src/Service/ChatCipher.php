<?php
declare(strict_types=1);
namespace App\Service;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Versioned authenticated encryption. Never reuse APP_SECRET as a chat key. */
final class ChatCipher
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir) {}
    private function key(): string {
        $path = $_ENV['CHAT_KEY_FILE'] ?? (getenv('CHAT_KEY_FILE') ?: $this->projectDir.'/var/private/chat.key');
        $encoded = is_file($path) ? file_get_contents($path) : false;
        $key = $encoded === false ? false : base64_decode(trim($encoded), true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new \RuntimeException('Chat encryption key is not configured.');
        }
        return $key;
    }
    public function encrypt(string $plain, string $context): string {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        return 'v1:'.base64_encode($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $context, $nonce, $this->key()));
    }
    public function decrypt(string $encrypted, string $context): string {
        if (!str_starts_with($encrypted, 'v1:')) { throw new \RuntimeException('Unsupported chat encryption version.'); }
        $bytes = base64_decode(substr($encrypted, 3), true);
        $length = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($bytes === false || strlen($bytes) < $length + 16) { throw new \RuntimeException('Invalid encrypted chat data.'); }
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($bytes, $length), $context, substr($bytes, 0, $length), $this->key());
        if ($plain === false) { throw new \RuntimeException('Chat authentication failed.'); }
        return $plain;
    }
}
