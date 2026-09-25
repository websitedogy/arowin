<?php
/**
 * Legacy path — redirect to /dashboard
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Location: ' . url('dashboard'), true, 301);
exit;
