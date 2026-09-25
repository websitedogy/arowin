<?php
/**
 * Consume one-time token and show member dashboard in this tab.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/collaborations.php';

$token = trim((string) ($_GET['token'] ?? ''));
$result = arowin_collab_consume_open_token($token);

if (empty($result['ok'])) {
    $_SESSION['arowin_collab_flash_err'] = $result['message'] ?? 'Could not open member dashboard.';
    header('Location: ' . arowin_url_with_account(arowin_abs($BASE, '/dashboard/team-collaborations')), true, 303);
    exit;
}

header('Location: ' . url('dashboard'), true, 303);
exit;
