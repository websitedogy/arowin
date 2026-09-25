<?php
/**
 * Transfer API
 * - GET  ?lookup=1&user_id=ARW… → recipient name/id preview
 * - POST JSON { csrf, to_user, amount, note? } → wallet transfer
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_API_MODE = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/wallet-repository.php';
require_once dirname(__DIR__, 2) . '/includes/withdrawal-settings-repository.php';
require_once dirname(__DIR__, 2) . '/includes/mail-service.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function arowin_transfer_json(array $payload, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if (!arowin_is_logged_in()) {
        arowin_transfer_json(['ok' => false, 'message' => 'Please sign in again.'], 401);
    }

    $username = (string) ($_SESSION['arowin_uid'] ?? '');
    $user = arowin_get_user_by_username($username);
    if (!$user) {
        arowin_transfer_json(['ok' => false, 'message' => 'Account not found.'], 404);
    }

    // Recipient lookup (for live name/id preview in transfer modal)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['lookup'])) {
        $toId = arowin_normalize_sponsor_id((string) ($_GET['user_id'] ?? ''));
        if ($toId === '') {
            arowin_transfer_json(['ok' => false, 'status' => 'invalid', 'message' => 'Enter a User ID.']);
        }
        if (strcasecmp($toId, $username) === 0) {
            arowin_transfer_json([
                'ok' => false,
                'status' => 'invalid',
                'message' => 'Cannot transfer to yourself.',
            ]);
        }
        if (function_exists('arowin_is_mother_id') && arowin_is_mother_id($toId)) {
            arowin_transfer_json([
                'ok' => false,
                'status' => 'invalid',
                'message' => 'Cannot transfer to mother ID.',
            ]);
        }
        $to = arowin_get_user_by_username($toId);
        if (!$to || in_array($toId, arowin_root_sponsor_ids(), true)) {
            arowin_transfer_json([
                'ok' => false,
                'status' => 'invalid',
                'message' => 'User ID not found.',
            ]);
        }
        arowin_transfer_json([
            'ok' => true,
            'status' => 'valid',
            'id' => (string) ($to['username'] ?? $toId),
            'name' => (string) ($to['full_name'] ?? ''),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        arowin_transfer_json([
            'ok' => true,
            'balance' => arowin_wallet_balance($username),
            'is_power_id' => trim((string) ($user['power_owner'] ?? '')) !== '',
            'message' => 'Transfer endpoint ready.',
        ]);
    }

    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    // Set withdrawal address permission (for Mother ID after transfer)
    if (isset($data['action']) && $data['action'] === 'set_withdrawal_permission') {
        if (!arowin_csrf_validate((string) ($data['csrf'] ?? ''))) {
            arowin_transfer_json(['ok' => false, 'message' => 'Session expired. Refresh and try again.'], 403);
        }
        if (!function_exists('arowin_is_mother_id') || !arowin_is_mother_id($username)) {
            arowin_transfer_json(['ok' => false, 'message' => 'Only Mother IDs can set withdrawal permissions.'], 403);
        }
        $targetUser = arowin_normalize_sponsor_id((string) ($data['target_user'] ?? ''));
        $allow = !empty($data['allow']);
        if ($targetUser === '') {
            arowin_transfer_json(['ok' => false, 'message' => 'Invalid target user.'], 400);
        }
        $result = arowin_set_withdrawal_address_permission($targetUser, $allow);
        $code = !empty($result['ok']) ? 200 : 400;
        arowin_transfer_json($result, $code);
    }

    if (!arowin_csrf_validate((string) ($data['csrf'] ?? ''))) {
        arowin_transfer_json(['ok' => false, 'message' => 'Session expired. Refresh and try again.'], 403);
    }

    if (trim((string) ($user['power_owner'] ?? '')) !== '') {
        arowin_transfer_json(['ok' => false, 'message' => 'Power IDs cannot transfer. Use your main ID.'], 403);
    }

    // Check if sender is Mother ID
    $senderIsMother = function_exists('arowin_is_mother_id') && arowin_is_mother_id($username);

    $toUser = arowin_normalize_sponsor_id((string) ($data['to_user'] ?? ''));
    $amount = round((float) ($data['amount'] ?? 0), 2);
    $note = trim((string) ($data['note'] ?? ''));

    $result = arowin_wallet_transfer($username, $toUser, $amount, $note);
    if (empty($result['ok'])) {
        arowin_send_transaction_failure_email($username, 'Transfer', (string) ($result['message'] ?? 'Transfer failed.'), [
            'Amount' => '$' . number_format($amount, 2) . ' USDT',
            'Recipient' => $toUser !== '' ? $toUser : '—',
        ]);
        arowin_transfer_json([
            'ok' => false,
            'message' => $result['message'] ?? 'Transfer failed.',
        ], 400);
    }

    $to = arowin_get_user_by_username($toUser);
    arowin_transfer_json([
        'ok' => true,
        'message' => $result['message'] ?? 'Transfer sent.',
        'balance' => $result['balance'] ?? arowin_wallet_balance($username),
        'to_user' => $toUser,
        'to_name' => (string) ($to['full_name'] ?? ''),
        'amount' => $amount,
        'sender_is_mother' => $senderIsMother,
    ]);
} catch (Throwable $e) {
    error_log('Transfer API error: ' . $e->getMessage());
    $failedUser = trim((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($failedUser !== '' && function_exists('arowin_send_transaction_failure_email')) {
        arowin_send_transaction_failure_email($failedUser, 'Transfer', 'Unexpected server error while processing the transfer.');
    }
    arowin_transfer_json([
        'ok' => false,
        'message' => env('APP_DEBUG', false) ? $e->getMessage() : 'Transfer failed. Try again.',
    ], 500);
}
