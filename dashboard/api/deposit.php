<?php
/**
 * Deposit API
 * - test mode: instant wallet credit
 * - nowpayments mode: create /v1/payment → unique address + QR fields
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_API_MODE = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/wallet-repository.php';
require_once dirname(__DIR__, 2) . '/includes/deposit-repository.php';
require_once dirname(__DIR__, 2) . '/includes/mail-service.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function arowin_deposit_json(array $payload, int $code = 200): void
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
        arowin_deposit_json(['ok' => false, 'message' => 'Please sign in again.'], 401);
    }

    $username = (string) ($_SESSION['arowin_uid'] ?? '');
    $user = arowin_get_user_by_username($username);
    if (!$user) {
        arowin_deposit_json(['ok' => false, 'message' => 'Account not found.'], 404);
    }

    $mode = strtolower(trim((string) env('DEPOSIT_MODE', 'test')));
    if ($mode === '') {
        $mode = 'test';
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        arowin_deposit_json([
            'ok' => true,
            'mode' => $mode,
            'balance' => arowin_wallet_balance($username),
            'currencies' => arowin_deposit_pay_currencies(),
            'message' => 'Deposit endpoint ready.',
        ]);
    }

    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    if (!arowin_csrf_validate((string) ($data['csrf'] ?? ''))) {
        arowin_deposit_json(['ok' => false, 'message' => 'Session expired. Refresh and try again.'], 403);
    }

    $amount = round((float) ($data['amount'] ?? 0), 2);
    $note = trim((string) ($data['note'] ?? ''));
    $payCurrency = trim((string) ($data['pay_currency'] ?? ''));

    if ($mode === 'nowpayments') {
        $result = arowin_deposit_create_nowpayments($username, $amount, $note, $payCurrency);
        if (empty($result['ok'])) {
            arowin_send_transaction_failure_email($username, 'Deposit', (string) ($result['message'] ?? 'Could not start deposit.'), [
                'Amount' => '$' . number_format($amount, 2) . ' USDT',
            ]);
            arowin_deposit_json([
                'ok' => false,
                'mode' => 'nowpayments',
                'message' => $result['message'] ?? 'Could not start the payment. Please try again.',
            ], 400);
        }
        arowin_deposit_json([
            'ok' => true,
            'mode' => 'nowpayments',
            'message' => $result['message'] ?? 'Payment initialized.',
            'order_id' => (string) ($result['order_id'] ?? ''),
            'payment_id' => (string) ($result['payment_id'] ?? ''),
            'pay_address' => (string) ($result['pay_address'] ?? ''),
            'pay_amount' => $result['pay_amount'] ?? null,
            'pay_currency' => (string) ($result['pay_currency'] ?? ''),
            'amount_usd' => (float) ($result['amount_usd'] ?? $amount),
            'status' => (string) ($result['status'] ?? 'waiting'),
            'qr_url' => (string) ($result['qr_url'] ?? ''),
            'redirect_url' => (string) ($result['redirect_url'] ?? ''),
            'checkout_mode' => (string) ($result['checkout_mode'] ?? 'payment'),
        ]);
    }

    $result = arowin_wallet_deposit($username, $amount, $note !== '' ? $note : 'Test deposit');
    if (empty($result['ok'])) {
        arowin_send_transaction_failure_email($username, 'Deposit', (string) ($result['message'] ?? 'Deposit failed.'), [
            'Amount' => '$' . number_format($amount, 2) . ' USDT',
        ]);
        arowin_deposit_json([
            'ok' => false,
            'mode' => 'test',
            'message' => $result['message'] ?? 'Deposit failed.',
        ], 400);
    }

    arowin_deposit_json([
        'ok' => true,
        'mode' => 'test',
        'message' => $result['message'],
        'balance' => (float) ($result['balance'] ?? arowin_wallet_balance($username)),
        'amount' => $amount,
    ]);
} catch (Throwable $e) {
    error_log('Deposit API error: ' . $e->getMessage());
    $failedUser = trim((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($failedUser !== '' && function_exists('arowin_send_transaction_failure_email')) {
        arowin_send_transaction_failure_email($failedUser, 'Deposit', 'Unexpected server error while processing the deposit.');
    }
    arowin_deposit_json([
        'ok' => false,
        'message' => env('APP_DEBUG', false)
            ? ('Deposit error: ' . $e->getMessage())
            : 'Deposit failed. Please try again.',
    ], 500);
}
