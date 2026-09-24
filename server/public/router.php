<?php

declare(strict_types=1);

// Let PHP's development server serve real public assets with their proper MIME type.
// API routes and Angular fallbacks continue through Symfony's front controller.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$file = is_string($path) ? realpath(__DIR__.rawurldecode($path)) : false;
$publicRoot = realpath(__DIR__);

if ($file !== false && $publicRoot !== false && str_starts_with($file, $publicRoot.DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

require __DIR__.'/index.php';
