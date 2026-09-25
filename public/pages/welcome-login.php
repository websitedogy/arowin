<?php
/**
 * One-time welcome dashboard open link from registration email
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_BOOT_LIGHT = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/welcome-login-repository.php';

$token = trim((string) ($_GET['token'] ?? ''));
$result = arowin_consume_welcome_login_token($token);

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (empty($result['ok']) || empty($result['user'])) {
    $_SESSION['arowin_flash'] = (string) ($result['message'] ?? 'Invalid dashboard link.');
    header('Location: ' . url('login'), true, 303);
    exit;
}

$welcomeUser = $result['user'];
$gate = arowin_user_can_sign_in($welcomeUser);
if (empty($gate['ok'])) {
    $_SESSION['arowin_flash'] = (string) $gate['message'];
    header('Location: ' . url('login'), true, 303);
    exit;
}

arowin_login_user($welcomeUser);
$_SESSION['arowin_flash'] = 'Welcome back, ' . (string) ($welcomeUser['full_name'] ?? '')
    . '! Your dashboard is ready.';

header('Location: ' . url('dashboard'), true, 303);
exit;
