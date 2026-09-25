<?php
/**
 * Withdrawal API
 * GET  → settings (password flag + addresses + balance)
 * POST JSON action: set_password | add_address | delete_address | withdraw
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_API_MODE = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/withdrawal-settings-repository.php';
require_once dirname(__DIR__, 2) . '/includes/wallet-repository.php';
require_once dirname(__DIR__, 2) . '/includes/nowpayments-service.php';
require_once dirname(__DIR__, 2) . '/includes/deposit-repository.php';
require_once dirname(__DIR__, 2) . '/includes/withdrawal-repository.php';
require_once dirname(__DIR__, 2) . '/includes/mail-service.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function arowin_wd_json(array $payload, int $code = 200): void
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
        arowin_wd_json(['ok' => false, 'message' => 'Please sign in again.'], 401);
    }

    $username = (string) ($_SESSION['arowin_uid'] ?? '');
    $user = arowin_get_user_by_username($username);
    if (!$user) {
        arowin_wd_json(['ok' => false, 'message' => 'Account not found.'], 404);
    }

    $settings = arowin_withdrawal_settings($username);
    $balance = arowin_wallet_balance($username);
    $isPower = trim((string) ($user['power_owner'] ?? '')) !== '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        arowin_wd_json([
            'ok' => true,
            'balance' => $balance,
            'has_password' => !empty($settings['has_password']),
            'addresses' => $settings['addresses'],
            'is_power' => $isPower,
            'can_add_address' => arowin_can_add_withdrawal_address($username),
            'min_withdraw' => (float) (arowin_nowpayments_config()['min_withdraw'] ?? 10),
            'max_withdraw' => (float) (arowin_nowpayments_config()['max_withdraw'] ?? 50000),
            'fee_percent' => function_exists('arowin_withdrawal_fee_percent') ? arowin_withdrawal_fee_percent() : 10.0,
            'currency' => (string) (arowin_nowpayments_config()['payout_currency'] ?? 'usdttrc20'),
            'settings_url' => url('operator-configuration'),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        arowin_wd_json(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    $raw = file_get_contents('php://input');
    $json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    $src = is_array($json) ? $json : $_POST;

    if (!arowin_csrf_validate((string) ($src['csrf'] ?? ''))) {
        arowin_wd_json(['ok' => false, 'message' => 'Session expired. Refresh and try again.'], 403);
    }

    $action = strtolower(trim((string) ($src['action'] ?? '')));

    if ($action === 'set_password') {
        $result = arowin_withdrawal_password_set(
            $username,
            (string) ($src['password'] ?? ''),
            (string) ($src['confirm_password'] ?? ''),
            (string) ($src['old_password'] ?? '')
        );
        if (empty($result['ok'])) {
            arowin_wd_json($result, 400);
        }
        $settings = arowin_withdrawal_settings($username);
        arowin_wd_json([
            'ok' => true,
            'message' => $result['message'],
            'has_password' => !empty($settings['has_password']),
        ]);
    }

    if ($action === 'add_address') {
        $result = arowin_withdrawal_address_add(
            $username,
            (string) ($src['address'] ?? ''),
            (string) ($src['label'] ?? ''),
            (string) ($src['currency'] ?? ''),
            !empty($src['is_default'])
        );
        if (empty($result['ok'])) {
            arowin_wd_json($result, 400);
        }
        arowin_wd_json([
            'ok' => true,
            'message' => $result['message'],
            'id' => $result['id'] ?? null,
            'addresses' => arowin_withdrawal_addresses_list($username),
        ]);
    }

    if ($action === 'delete_address') {
        $result = arowin_withdrawal_address_delete($username, (int) ($src['id'] ?? 0));
        if (empty($result['ok'])) {
            arowin_wd_json($result, 400);
        }
        arowin_wd_json([
            'ok' => true,
            'message' => $result['message'],
            'addresses' => arowin_withdrawal_addresses_list($username),
        ]);
    }

    if ($action === 'update_address') {
        $result = arowin_withdrawal_address_update(
            $username,
            (int) ($src['id'] ?? 0),
            (string) ($src['address'] ?? ''),
            (string) ($src['label'] ?? ''),
            (string) ($src['currency'] ?? 'usdtbsc')
        );
        if (empty($result['ok'])) {
            arowin_wd_json($result, 400);
        }
        arowin_wd_json([
            'ok' => true,
            'message' => $result['message'],
            'addresses' => arowin_withdrawal_addresses_list($username),
        ]);
    }

    if ($action === 'withdraw') {
        if ($isPower) {
            arowin_wd_json(['ok' => false, 'message' => 'Power IDs cannot withdraw. Use your main ID.'], 403);
        }
        $result = arowin_withdrawal_submit(
            $username,
            (float) ($src['amount'] ?? 0),
            (int) ($src['address_id'] ?? 0),
            (string) ($src['firebase_token'] ?? '')
        );
        $code = !empty($result['ok']) ? 200 : 400;
        $result['balance'] = (float) ($result['balance'] ?? arowin_wallet_balance($username));
        if (empty($result['ok'])) {
            arowin_send_transaction_failure_email($username, 'Withdrawal', (string) ($result['message'] ?? 'Withdrawal failed.'), [
                'Amount' => '$' . number_format((float) ($src['amount'] ?? 0), 2) . ' USDT',
                'Status' => 'Failed',
            ]);
        }
        arowin_wd_json($result, $code);
    }

    arowin_wd_json(['ok' => false, 'message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('Withdrawal API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $failedUser = trim((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($failedUser !== '' && function_exists('arowin_send_transaction_failure_email')) {
        arowin_send_transaction_failure_email($failedUser, 'Withdrawal', 'Unexpected server error while processing the withdrawal.');
    }
    arowin_wd_json(['ok' => false, 'message' => 'Server error. Try again.'], 500);
}
