<?php
/**
 * Arowin — Logout (Sign Out clears ALL member accounts in this browser session)
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

// Sign Out = full logout (not leave other IDs in the bag — that looked like "logout failed")
arowin_logout_user('*');

// Stop PWA / remembered-device auto PIN from signing the user back in immediately
if (function_exists('arowin_security_remember_device')) {
    arowin_security_remember_device('', false);
}
unset($_SESSION['arowin_2fa_pending']);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

// password=1 blocks remembered-device auto unlock on the login page
header('Location: ' . url('login') . '?password=1&logged_out=1', true, 303);
exit;
