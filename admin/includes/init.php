<?php
/**
 * Admin panel bootstrap — session + app init + no-store headers.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // Cookie path for /arowin subdirectory installs
    $cookiePath = '/';
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.*?)/admin(?:/|$)#', $scriptName, $m) === 1) {
        $base = rtrim($m[1], '/');
        $cookiePath = ($base === '') ? '/' : $base . '/';
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath,
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$AROWIN_BOOT_ADMIN = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/admin-users-repository.php';
require_once dirname(__DIR__, 2) . '/includes/admin-metrics-repository.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/pagination.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
