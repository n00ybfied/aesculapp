<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Resolves the encryption key used for all private customer data. */
final class PrivateDataKeyPath
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    public function get(): string
    {
        foreach (['PRIVATE_DATA_KEY_FILE', 'CHAT_KEY_FILE'] as $variable) {
            $configured = $_ENV[$variable] ?? getenv($variable);
            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        $current = $this->projectDir . '/var/private/private-data.key';
        if (is_file($current)) {
            return $current;
        }

        // Temporary compatibility for deployments which have not moved the file yet.
        $legacy = $this->projectDir . '/var/private/chat.key';
        return is_file($legacy) ? $legacy : $current;
    }
}
