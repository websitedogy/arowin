<?php
/**
 * Legacy URL → new History feature folder
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
$target = arowin_abs($BASE, '/dashboard/history/');
if ($qs !== '') {
    $target .= '?' . $qs;
}
header('Location: ' . $target, true, 301);
exit;
