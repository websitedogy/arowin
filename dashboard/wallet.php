<?php
/**
 * Legacy /wallet → /transfer
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__) . '/includes/bootstrap.php';

header('Location: ' . url('transfer'), true, 301);
exit;
