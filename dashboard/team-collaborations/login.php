<?php
/**
 * Team Collaborations — issue one-time token, then open member dashboard in this tab.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/collaborations.php';

$tab = arowin_collab_normalize_tab((string) ($_POST['tab'] ?? $_GET['tab'] ?? 'all'));
$back = arowin_url_with_account(arowin_abs(
    $BASE,
    '/dashboard/team-collaborations' . ($tab !== 'all' ? ('?tab=' . rawurlencode($tab)) : '')
));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back, true, 303);
    exit;
}

$sessionUid = (string) ($_SESSION['arowin_uid'] ?? '');
if ($sessionUid === '' || !arowin_is_logged_in()) {
    header('Location: ' . url('login'), true, 303);
    exit;
}

// Collaborations list always belongs to home operator
arowin_collab_ensure_home_session();
$actor = (string) ($_SESSION['arowin_uid'] ?? $sessionUid);
$actorUser = arowin_get_user_by_username($actor);
if (!$actorUser || !function_exists('arowin_user_can_view_power_ids') || !arowin_user_can_view_power_ids($actorUser)) {
    header('Location: ' . url('direct-referrals'), true, 303);
    exit;
}

if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
    $_SESSION['arowin_collab_flash_err'] = 'Session expired. Refresh and try again.';
    header('Location: ' . $back, true, 303);
    exit;
}

$target = arowin_normalize_sponsor_id((string) ($_POST['target_id'] ?? ''));
$result = arowin_collab_issue_open_token($actor, $target);
if (empty($result['ok']) || empty($result['token'])) {
    $_SESSION['arowin_collab_flash_err'] = $result['message'] ?? 'Could not open member dashboard.';
    header('Location: ' . $back, true, 303);
    exit;
}

$openUrl = arowin_abs(
    $BASE,
    '/dashboard/team-collaborations/open.php?token=' . rawurlencode((string) $result['token'])
);
header('Location: ' . $openUrl, true, 303);
exit;
