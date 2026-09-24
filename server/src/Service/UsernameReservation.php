<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/** Prevents a newly assigned username from inheriting an unexpired JWT for its former owner. */
final class UsernameReservation
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function isReserved(string $username): bool
    {
        $expiresAt = $this->connection->fetchOne(
            'SELECT expires_at FROM reserved_username WHERE username_hash = ?',
            [hash('sha256', mb_strtolower(trim($username)))],
        );
        return is_string($expiresAt) && $expiresAt > (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    public function reserve(string $username): void
    {
        $this->connection->executeStatement(
            'INSERT INTO reserved_username (username_hash, expires_at) VALUES (?, ?) ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)',
            [hash('sha256', mb_strtolower(trim($username))), (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s')],
        );
    }
}
