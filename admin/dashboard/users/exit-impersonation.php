<?php
/**
 * Admin — leave "login as user" and return to users list
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

arowin_admin_clear_impersonation();
arowin_logout_user();

$back = admin_url('/dashboard/users');
$status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (in_array($status, ['pending', 'active', 'inactive'], true)) {
    $back .= '?status=' . rawurlencode($status);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Location: ' . $back, true, 303);
exit;
