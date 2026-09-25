<?php
/**
 * Admin — login as registered user (opens member dashboard)
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';

$back = admin_url('/dashboard/users');
$status = strtolower(trim((string) ($_POST['status'] ?? $_GET['status'] ?? 'all')));
if (in_array($status, ['pending', 'active', 'inactive', 'all'], true) && $status !== 'all') {
    $back .= '?status=' . rawurlencode($status);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_redirect('/dashboard/users');
}

if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
    $_SESSION['arowin_admin_users_flash_err'] = 'Session expired. Refresh and try again.';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $back, true, 303);
    exit;
}

$username = arowin_normalize_sponsor_id((string) ($_POST['username'] ?? ''));
$result = arowin_admin_login_as_user($username);

if (empty($result['ok'])) {
    $_SESSION['arowin_admin_users_flash_err'] = (string) ($result['message'] ?? 'Login as user failed.');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $back, true, 303);
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Location: ' . url('dashboard'), true, 303);
exit;
