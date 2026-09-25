<?php
/**
 * Team Collaborations — send all Power ID wallets to the master ID.
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

arowin_collab_ensure_home_session();
$actor = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? $sessionUid));
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

$result = arowin_collab_transfer_all_to_master($actor);
if (empty($result['ok'])) {
    $_SESSION['arowin_collab_flash_err'] = (string) ($result['message'] ?? 'Transfer failed.');
} else {
    $_SESSION['arowin_collab_flash_ok'] = (string) ($result['message'] ?? 'Sent to master wallet.');
}

header('Location: ' . $back, true, 303);
exit;
