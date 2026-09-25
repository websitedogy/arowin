<?php
/**
 * Fast deposit start — native form POST, then 302 to NOWPayments (no JSON wait).
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_API_MODE = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/deposit-repository.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Location: ' . url('dashboard'), true, 303);
    exit;
}

if (!arowin_is_logged_in()) {
    header('Location: ' . url('login'), true, 303);
    exit;
}

if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
    $_SESSION['arowin_flash'] = 'Session expired. Refresh and try deposit again.';
    header('Location: ' . url('dashboard'), true, 303);
    exit;
}

$mode = strtolower(trim((string) env('DEPOSIT_MODE', 'test')));
if ($mode !== 'nowpayments') {
    header('Location: ' . url('deposit'), true, 303);
    exit;
}

$username = (string) ($_SESSION['arowin_uid'] ?? '');
$amount = round((float) ($_POST['amount'] ?? 0), 2);
$note = trim((string) ($_POST['note'] ?? ''));

$result = arowin_deposit_create_nowpayments($username, $amount, $note);
$redirect = trim((string) ($result['redirect_url'] ?? ''));

if (!empty($result['ok']) && $redirect !== '') {
    header('Location: ' . $redirect, true, 302);
    exit;
}

$_SESSION['arowin_flash'] = (string) ($result['message'] ?? 'Could not start payment. Try again.');
header('Location: ' . url('dashboard') . '?deposit=error', true, 303);
exit;
