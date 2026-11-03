<?php declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
]);

require __DIR__ . '/../../config.php';
require __DIR__ . '/helpers.php';

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
