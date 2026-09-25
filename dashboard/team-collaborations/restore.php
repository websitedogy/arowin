<?php
/**
 * Return from member dashboard back to the original operator ID.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/collaborations.php';

if (!arowin_is_logged_in()) {
    header('Location: ' . url('login'), true, 303);
    exit;
}

arowin_collab_restore_home();

$go = trim((string) ($_GET['to'] ?? 'collaborations'));
if ($go === 'dashboard') {
    header('Location: ' . url('dashboard'), true, 303);
    exit;
}

header('Location: ' . arowin_url_with_account(arowin_abs($BASE, '/dashboard/team-collaborations')), true, 303);
exit;
