<?php
/**
 * NOWPayments IPN webhook
 * - Deposits: credit wallet when payment is confirming/confirmed/finished (as soon as NP sees pay)
 * - Payouts: update withdrawal status / tx hash / refund on failure
 * HMAC-SHA512 verified via NOWPAYMENTS_IPN_SECRET
 */
declare(strict_types=1);

$AROWIN_API_MODE = true;
$AROWIN_BOOT_LIGHT = false;

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/wallet-repository.php';
require_once dirname(__DIR__, 2) . '/includes/nowpayments-service.php';
require_once dirname(__DIR__, 2) . '/includes/deposit-repository.php';
require_once dirname(__DIR__, 2) . '/includes/withdrawal-repository.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function arowin_ipn_reply(array $payload, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    arowin_ipn_reply(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$raw = file_get_contents('php://input');
if ($raw === false || trim($raw) === '') {
    arowin_ipn_reply(['ok' => false, 'message' => 'Empty body.'], 400);
}

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    arowin_ipn_reply(['ok' => false, 'message' => 'Invalid JSON.'], 400);
}

$sig = (string) (
    $_SERVER['HTTP_X_NOWPAYMENTS_SIG']
    ?? $_SERVER['REDIRECT_HTTP_X_NOWPAYMENTS_SIG']
    ?? ''
);

$np = arowin_nowpayments();
if (!$np->hasIpnSecret()) {
    error_log('NOWPayments IPN: NOWPAYMENTS_IPN_SECRET missing');
    arowin_nowpayments_log_webhook('config_error', null, false, $payload, ['message' => 'missing secret']);
    arowin_ipn_reply(['ok' => false, 'message' => 'IPN secret not configured.'], 503);
}

$sigOk = $np->verifyIpnSignature($payload, $sig);
if (!$sigOk) {
    error_log('NOWPayments IPN: invalid signature');
    arowin_nowpayments_log_webhook('bad_signature', null, false, $payload);
    arowin_ipn_reply(['ok' => false, 'message' => 'Invalid signature.'], 403);
}

try {
    $isPayout = arowin_nowpayments_is_payout_ipn($payload);
    if ($isPayout) {
        $result = arowin_withdrawal_handle_ipn($payload);
        $ext = (string) (
            $payload['id']
            ?? $payload['batch_withdrawal_id']
            ?? ($payload['withdrawals'][0]['id'] ?? '')
        );
        arowin_nowpayments_log_webhook('payout', $ext !== '' ? $ext : null, true, $payload, $result);
    } else {
        $result = arowin_deposit_handle_ipn($payload);
        $ext = (string) ($payload['payment_id'] ?? $payload['order_id'] ?? '');
        arowin_nowpayments_log_webhook('deposit', $ext !== '' ? $ext : null, true, $payload, $result);
    }

    if (empty($result['ok'])) {
        arowin_ipn_reply([
            'ok' => false,
            'message' => (string) ($result['message'] ?? 'IPN handling failed.'),
        ], 422);
    }

    arowin_ipn_reply([
        'ok' => true,
        'type' => $isPayout ? 'payout' : 'deposit',
        'message' => (string) ($result['message'] ?? 'OK'),
        'credited' => !empty($result['credited']),
    ]);
} catch (Throwable $e) {
    error_log('NOWPayments IPN exception: ' . $e->getMessage());
    arowin_ipn_reply(['ok' => false, 'message' => 'Server error.'], 500);
}
