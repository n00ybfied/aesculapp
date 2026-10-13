<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

$apiDirectory = getcwd();
if ($apiDirectory === false || !str_ends_with($apiDirectory, '/web/aesculapp-api')) {
    fwrite(STDERR, "Run this from the pharmacy API directory.\n");
    exit(1);
}

require $apiDirectory.'/vendor/autoload.php';
(new Dotenv())->loadEnv($apiDirectory.'/.env');

$databaseUrl = $_ENV['DATABASE_URL'] ?? null;
$database = is_string($databaseUrl) ? parse_url($databaseUrl) : false;
if (!is_array($database) || !isset($database['host'], $database['user'], $database['pass'], $database['path'])) {
    fwrite(STDERR, "Database connection is incomplete.\n");
    exit(1);
}

$backupDirectory = dirname($apiDirectory, 2).'/backups/aesculapp';
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true)) {
    fwrite(STDERR, "Cannot create backup directory.\n");
    exit(1);
}
chmod($backupDirectory, 0700);
umask(0077);

$timestamp = gmdate('Ymd-His');
$backupPath = $backupDirectory.'/apotheke-test-predeploy-'.$timestamp.'.sql';
$temporaryPath = $backupPath.'.partial';
if (file_exists($backupPath) || file_exists($temporaryPath)) {
    fwrite(STDERR, "Backup path already exists.\n");
    exit(1);
}

$optionFile = tempnam($backupDirectory, '.mysql-');
if ($optionFile === false) {
    fwrite(STDERR, "Cannot create temporary MySQL configuration.\n");
    exit(1);
}

$quoteOption = static fn (string $value): string => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
$options = "[client]\n"
    .'host='.$quoteOption($database['host'])."\n"
    .'user='.$quoteOption(rawurldecode($database['user']))."\n"
    .'password='.$quoteOption(rawurldecode($database['pass']))."\n";

try {
    if (file_put_contents($optionFile, $options) === false) {
        throw new RuntimeException('Cannot write temporary MySQL configuration.');
    }
    chmod($optionFile, 0600);

    $command = [
        '/usr/bin/mysqldump',
        '--defaults-extra-file='.$optionFile,
        '--single-transaction',
        '--quick',
        '--default-character-set=utf8mb4',
        '--hex-blob',
        '--triggers',
        ltrim(rawurldecode($database['path']), '/'),
    ];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $temporaryPath, 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start mysqldump.');
    }
    fclose($pipes[0]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('mysqldump failed: '.trim((string) $errors));
    }
    if (!is_file($temporaryPath) || filesize($temporaryPath) === 0) {
        throw new RuntimeException('Database backup is empty.');
    }
    chmod($temporaryPath, 0600);
    if (!rename($temporaryPath, $backupPath)) {
        throw new RuntimeException('Cannot finalize database backup.');
    }
    fwrite(STDOUT, basename($backupPath).' '.filesize($backupPath).' bytes '.hash_file('sha256', $backupPath)."\n");
} catch (Throwable $exception) {
    if (is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} finally {
    unlink($optionFile);
}
