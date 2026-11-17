<?php
declare(strict_types=1);
// Router for PHP's built-in server. Run with: php -S 127.0.0.1:8080 -t public server.php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__ . '/public' . $path)) {
    return false; // let the built-in server stream the static file from -t public
}
require __DIR__ . '/public/index.php';
