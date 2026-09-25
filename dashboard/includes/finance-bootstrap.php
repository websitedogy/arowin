<?php
/**
 * Shared auth + assets bootstrap for finance pages
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$sessionUid = (string) ($_SESSION['arowin_uid'] ?? '');
if ($sessionUid === '' || !arowin_is_logged_in()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$user = arowin_get_user_by_username($sessionUid);
if (!$user || (int) ($user['id'] ?? 0) < 1) {
    arowin_logout_user();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$view = arowin_dashboard_view_data($user);

$cssHref = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$cssFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$jsFile  = dirname(__DIR__, 2) . '/dashboard/assets/js/dashboard.js';
if (is_file($cssFile)) {
    $cssHref .= '?v=' . filemtime($cssFile);
}
if (is_file($jsFile)) {
    $jsHref .= '?v=' . filemtime($jsFile);
}
