<?php
/**
 * Wallet deposits via NOWPayments — schema, create payment, IPN credit
 */
declare(strict_types=1);

require_once __DIR__ . '/crypto-helpers.php';
require_once __DIR__ . '/wallet-repository.php';

/**
 * Supported deposit networks for in-app QR flow.
 *
 * @return list<array{code:string,label:string}>
 */
function arowin_deposit_pay_currencies(): array
{
    return [
        ['code' => 'usdtbsc', 'label' => 'USDT-BEP20'],
        ['code' => 'usdttrc20', 'label' => 'USDT-TRC20'],
        ['code' => 'btc', 'label' => 'BTC'],
        ['code' => 'eth', 'label' => 'ETH'],
        ['code' => 'trx', 'label' => 'TRX'],
    ];
}

function arowin_deposit_normalize_pay_currency(?string $code): string
{
    $code = strtolower(trim((string) $code));
    $allowed = array_column(arowin_deposit_pay_currencies(), 'code');
    if ($code !== '' && in_array($code, $allowed, true)) {
        return $code;
    }
    $cfg = function_exists('arowin_nowpayments_config') ? arowin_nowpayments_config() : [];
    $fallback = strtolower((string) ($cfg['pay_currency'] ?? 'usdtbsc'));
    return in_array($fallback, $allowed, true) ? $fallback : 'usdtbsc';
}

function arowin_ensure_deposits_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo = arowin_db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `wallet_deposits` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `order_id` VARCHAR(80) NOT NULL,
          `amount_usd` DECIMAL(14,2) NOT NULL,
          `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
          `nowpayments_payment_id` VARCHAR(64) DEFAULT NULL,
          `nowpayments_invoice_id` VARCHAR(64) DEFAULT NULL,
          `pay_currency` VARCHAR(32) DEFAULT NULL,
          `pay_amount` DECIMAL(24,12) DEFAULT NULL,
          `pay_address` VARCHAR(128) DEFAULT NULL,
          `actually_paid` DECIMAL(24,12) DEFAULT NULL,
          `payment_url` VARCHAR(500) DEFAULT NULL,
          `note` VARCHAR(255) DEFAULT NULL,
          `raw_ipn_json` MEDIUMTEXT DEFAULT NULL,
          `credited_at` TIMESTAMP NULL DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_deposit_order` (`order_id`),
          UNIQUE KEY `uq_deposit_payment` (`nowpayments_payment_id`),
          KEY `idx_deposit_user` (`username`),
          KEY `idx_deposit_status` (`status`),
          KEY `idx_deposit_invoice` (`nowpayments_invoice_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Upgrade existing installs
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM wallet_deposits')->fetchAll(PDO::FETCH_COLUMN);
        $set = array_flip(array_map('strval', $cols ?: []));
        if (!isset($set['pay_address'])) {
            $pdo->exec('ALTER TABLE `wallet_deposits` ADD COLUMN `pay_address` VARCHAR(128) DEFAULT NULL AFTER `pay_amount`');
        }
        if (!isset($set['reversed_at'])) {
            $pdo->exec('ALTER TABLE `wallet_deposits` ADD COLUMN `reversed_at` TIMESTAMP NULL DEFAULT NULL AFTER `credited_at`');
        }
    } catch (Throwable $e) {
        // ignore
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `nowpayments_webhook_logs` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `event_type` VARCHAR(40) NOT NULL DEFAULT 'unknown',
          `external_id` VARCHAR(80) DEFAULT NULL,
          `signature_ok` TINYINT(1) NOT NULL DEFAULT 0,
          `payload_json` MEDIUMTEXT NOT NULL,
          `result_json` TEXT DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_np_wh_ext` (`external_id`),
          KEY `idx_np_wh_type` (`event_type`),
          KEY `idx_np_wh_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function arowin_deposit_app_base(): string
{
    $base = rtrim((string) (isset($GLOBALS['BASE']) ? $GLOBALS['BASE'] : env('APP_URL', '')), '/');
    if ($base === '') {
        $base = rtrim(arowin_request_base_url(), '/');
    }
    return $base;
}

function arowin_deposit_ipn_url(): string
{
    $configured = trim((string) env('NOWPAYMENTS_IPN_URL', ''));
    if ($configured !== '') {
        return $configured;
    }
    return arowin_deposit_app_base() . '/dashboard/api/nowpayments-ipn.php';
}

function arowin_deposit_success_url(): string
{
    return arowin_deposit_app_base() . '/dashboard/?deposit=success';
}

function arowin_deposit_cancel_url(): string
{
    return arowin_deposit_app_base() . '/dashboard/?deposit=cancelled';
}

function arowin_deposit_checkout_url(string $orderId): string
{
    return arowin_deposit_app_base() . '/dashboard/deposit/checkout?order_id=' . rawurlencode($orderId);
}

function arowin_deposit_make_order_id(string $username): string
{
    $username = arowin_normalize_sponsor_id($username);
    return 'DEP-' . $username . '-' . strtoupper(bin2hex(random_bytes(6)));
}

/**
 * Log webhook (best-effort).
 *
 * @param array<string,mixed> $payload
 * @param array<string,mixed> $result
 */
function arowin_nowpayments_log_webhook(string $eventType, ?string $externalId, bool $sigOk, array $payload, array $result = []): void
{
    try {
        arowin_ensure_deposits_schema();
        $pj = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $rj = $result !== [] ? json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        arowin_db()->prepare(
            'INSERT INTO nowpayments_webhook_logs (event_type, external_id, signature_ok, payload_json, result_json)
             VALUES (:t, :e, :s, :p, :r)'
        )->execute([
            ':t' => mb_substr($eventType, 0, 40),
            ':e' => $externalId !== null && $externalId !== '' ? mb_substr($externalId, 0, 80) : null,
            ':s' => $sigOk ? 1 : 0,
            ':p' => $pj !== false ? $pj : '{}',
            ':r' => $rj,
        ]);
    } catch (Throwable $e) {
        error_log('NOWPayments webhook log failed: ' . $e->getMessage());
    }
}

/**
 * Start a NOWPayments deposit via Payment API (/v1/payment) — unique address per order.
 *
 * @return array{
 *   ok:bool, message:string, mode?:string, redirect_url?:string, order_id?:string,
 *   payment_id?:string, pay_address?:string, pay_amount?:float|null, pay_currency?:string,
 *   amount_usd?:float, status?:string, qr_url?:string
 * }
 */
function arowin_deposit_create_nowpayments(
    string $username,
    float $amount,
    string $note = '',
    ?string $payCurrency = null
): array {
    arowin_ensure_deposits_schema();
    require_once __DIR__ . '/nowpayments-service.php';

    $username = arowin_normalize_sponsor_id($username);
    $cfg = arowin_nowpayments_config();
    $min = (float) ($cfg['min_deposit'] ?? 1);
    $max = (float) ($cfg['max_deposit'] ?? 100000);
    $note = trim($note);
    $payCurrency = arowin_deposit_normalize_pay_currency($payCurrency);

    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }

    $amtCheck = arowin_money_ok($amount, $min, $max, 'Deposit');
    if (empty($amtCheck['ok'])) {
        return $amtCheck;
    }
    $amount = (float) $amtCheck['amount'];

    $user = arowin_get_user_by_username($username);
    if (!$user || (int) ($user['id'] ?? 0) < 1) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }
    if (!empty($user['power_owner'])) {
        return ['ok' => false, 'message' => 'Power IDs cannot deposit. Use your main ID.'];
    }

    $np = arowin_nowpayments();
    if (!$np->isConfigured()) {
        return ['ok' => false, 'message' => 'The payment service is not configured yet. Please try again later.'];
    }

    $orderId = arowin_deposit_make_order_id($username);
    $pdo = arowin_db();

    // In-app QR scanner uses Payment API. Invoice mode only if explicitly requested.
    $checkoutMode = strtolower((string) ($cfg['checkout_mode'] ?? 'payment'));
    if (!in_array($checkoutMode, ['payment', 'invoice'], true)) {
        $checkoutMode = 'payment';
    }

    $ins = $pdo->prepare(
        'INSERT INTO wallet_deposits
            (username, order_id, amount_usd, status, note, pay_currency)
         VALUES
            (:u, :o, :a, \'pending\', :n, :pc)'
    );
    $ins->execute([
        ':u' => $username,
        ':o' => $orderId,
        ':a' => $amount,
        ':n' => $note !== '' ? mb_substr($note, 0, 255) : null,
        ':pc' => $payCurrency,
    ]);

    $ipnUrl = arowin_deposit_ipn_url();
    $common = [
        'price_amount' => $amount,
        'price_currency' => 'usd',
        'order_id' => $orderId,
        'order_description' => 'Arowin wallet deposit ' . $orderId,
        'ipn_callback_url' => $ipnUrl,
        'pay_currency' => $payCurrency,
    ];

    if ($checkoutMode === 'invoice') {
        $created = $np->createInvoice(array_merge($common, [
            'success_url' => arowin_deposit_success_url(),
            'cancel_url' => arowin_deposit_cancel_url(),
        ]));
    } else {
        $created = $np->createPayment($common);
    }

    if (empty($created['ok']) || empty($created['data'])) {
        $pdo->prepare(
            "UPDATE wallet_deposits SET status = 'failed', note = :n WHERE order_id = :o LIMIT 1"
        )->execute([
            ':n' => mb_substr('Create failed: ' . (string) ($created['message'] ?? 'unknown'), 0, 255),
            ':o' => $orderId,
        ]);
        return [
            'ok' => false,
            'message' => (string) ($created['message'] ?? 'Could not create the payment.'),
            'mode' => 'nowpayments',
        ];
    }

    $data = $created['data'];
    $paymentId = isset($data['payment_id']) ? (string) $data['payment_id'] : '';
    $invoiceId = '';
    if (isset($data['id']) && $checkoutMode === 'invoice') {
        $invoiceId = (string) $data['id'];
    } elseif (isset($data['invoice_id'])) {
        $invoiceId = (string) $data['invoice_id'];
    }

    $payAddress = trim((string) ($data['pay_address'] ?? ''));
    $payAmount = isset($data['pay_amount']) ? (float) $data['pay_amount'] : null;
    $respCurrency = strtolower(trim((string) ($data['pay_currency'] ?? $payCurrency)));
    $status = strtolower(trim((string) ($data['payment_status'] ?? 'waiting')));
    if ($status === '') {
        $status = 'waiting';
    }

    $paymentUrl = trim((string) ($data['invoice_url'] ?? $data['payment_url'] ?? ''));
    if ($paymentUrl === '') {
        $paymentUrl = arowin_deposit_checkout_url($orderId);
    }

    $pdo->prepare(
        'UPDATE wallet_deposits
         SET nowpayments_payment_id = :pid,
             nowpayments_invoice_id = :iid,
             pay_currency = :pc,
             pay_amount = :pa,
             pay_address = :addr,
             payment_url = :url,
             status = :st
         WHERE order_id = :o
         LIMIT 1'
    )->execute([
        ':pid' => $paymentId !== '' ? $paymentId : null,
        ':iid' => $invoiceId !== '' ? $invoiceId : null,
        ':pc' => $respCurrency !== '' ? mb_substr($respCurrency, 0, 32) : $payCurrency,
        ':pa' => $payAmount,
        ':addr' => $payAddress !== '' ? mb_substr($payAddress, 0, 128) : null,
        ':url' => mb_substr($paymentUrl, 0, 500),
        ':st' => mb_substr($status, 0, 32),
        ':o' => $orderId,
    ]);

    $qrPayload = $payAddress !== '' ? $payAddress : $paymentUrl;
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data='
        . rawurlencode($qrPayload);

    return [
        'ok' => true,
        'message' => $payAddress !== ''
            ? 'Payment initialized. Send the exact amount to the address below.'
            : 'Payment created. Complete checkout to finish deposit.',
        'mode' => 'nowpayments',
        'redirect_url' => $paymentUrl,
        'order_id' => $orderId,
        'payment_id' => $paymentId,
        'pay_address' => $payAddress,
        'pay_amount' => $payAmount,
        'pay_currency' => $respCurrency !== '' ? $respCurrency : $payCurrency,
        'amount_usd' => $amount,
        'status' => $status,
        'qr_url' => $qrUrl,
        'checkout_mode' => $checkoutMode,
    ];
}

/**
 * NOWPayments statuses that mean payment was received — credit wallet immediately.
 * (Do not wait only for final "finished" — users feel the deposit is instant.)
 */
function arowin_deposit_status_is_creditable(
    string $status,
    ?float $actuallyPaid = null,
    ?float $expectedPay = null
): bool {
    $status = strtolower(trim($status));
    // confirming = NP already saw the on-chain tx. Reversal runs if NP later fails/refunds.
    if (in_array($status, ['confirming', 'confirmed', 'sending', 'finished'], true)) {
        return true;
    }
    if (
        $status === 'partially_paid'
        && $actuallyPaid !== null
        && $expectedPay !== null
        && $expectedPay > 0
        && ($actuallyPaid + 1e-12) >= ($expectedPay * 0.98)
    ) {
        return true;
    }
    return false;
}

function arowin_deposit_status_is_failed(string $status): bool
{
    return in_array(strtolower(trim($status)), ['failed', 'refunded', 'expired'], true);
}

/**
 * Refresh deposit status from NOWPayments (and return public fields).
 *
 * @return array{ok:bool, message:string, status?:string, credited?:bool, balance?:float, pay_address?:string, pay_amount?:float|null, pay_currency?:string, tx_hash?:string}
 */
function arowin_deposit_refresh_status(string $orderId, string $username): array
{
    arowin_ensure_deposits_schema();
    require_once __DIR__ . '/nowpayments-service.php';

    $orderId = trim($orderId);
    $username = arowin_normalize_sponsor_id($username);
    $row = arowin_deposit_find_by_order($orderId);
    if (!$row || strcasecmp((string) $row['username'], $username) !== 0) {
        return ['ok' => false, 'message' => 'Deposit order not found.'];
    }

    $status = strtolower((string) ($row['status'] ?? ''));
    $credited = !empty($row['credited_at']);

    $paymentId = trim((string) ($row['nowpayments_payment_id'] ?? ''));
    if (!$credited) {
        $np = arowin_nowpayments();
        $payload = null;
        if ($paymentId !== '') {
            $remote = $np->getPaymentStatus($paymentId);
            if (!empty($remote['ok']) && !empty($remote['data']) && is_array($remote['data'])) {
                $payload = $remote['data'];
            }
        }
        if ($payload === null && method_exists($np, 'findPaymentByOrderId')) {
            $found = $np->findPaymentByOrderId($orderId);
            if (!empty($found['ok']) && !empty($found['data']) && is_array($found['data'])) {
                $payload = $found['data'];
            }
        }
        if (is_array($payload)) {
            if (empty($payload['payment_status']) && !empty($payload['status'])) {
                $payload['payment_status'] = $payload['status'];
            }
            if (empty($payload['order_id'])) {
                $payload['order_id'] = $orderId;
            }
            arowin_deposit_handle_ipn($payload);
            $row = arowin_deposit_find_by_order($orderId) ?: $row;
            $status = strtolower((string) ($row['status'] ?? $status));
            $credited = !empty($row['credited_at']);
        }
    }

    $payAddress = trim((string) ($row['pay_address'] ?? ''));
    $qrUrl = $payAddress !== ''
        ? ('https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=' . rawurlencode($payAddress))
        : '';

    return [
        'ok' => true,
        'message' => $credited ? 'Payment received. Wallet credited.' : ('Status: ' . $status),
        'order_id' => $orderId,
        'status' => $status,
        'credited' => $credited,
        'balance' => arowin_wallet_balance($username),
        'pay_address' => $payAddress,
        'pay_amount' => $row['pay_amount'] !== null ? (float) $row['pay_amount'] : null,
        'pay_currency' => (string) ($row['pay_currency'] ?? ''),
        'amount_usd' => round((float) ($row['amount_usd'] ?? 0), 2),
        'qr_url' => $qrUrl,
        'payment_id' => (string) ($row['nowpayments_payment_id'] ?? ''),
    ];
}

/**
 * @return array<string,mixed>|null
 */
function arowin_deposit_find_by_order(string $orderId): ?array
{
    arowin_ensure_deposits_schema();
    $orderId = trim($orderId);
    if ($orderId === '') {
        return null;
    }
    $stmt = arowin_db()->prepare('SELECT * FROM wallet_deposits WHERE order_id = :o LIMIT 1');
    $stmt->execute([':o' => $orderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Handle verified deposit IPN — credit as soon as NOWPayments detects/confirms payment.
 *
 * @param array<string,mixed> $payload
 * @return array{ok:bool, message:string, credited?:bool}
 */
function arowin_deposit_handle_ipn(array $payload): array
{
    arowin_ensure_deposits_schema();

    $orderId = trim((string) ($payload['order_id'] ?? ''));
    $paymentId = trim((string) ($payload['payment_id'] ?? ''));
    $status = strtolower(trim((string) ($payload['payment_status'] ?? '')));
    if ($status === '') {
        $status = strtolower(trim((string) ($payload['status'] ?? '')));
    }
    $payCurrency = strtolower(trim((string) ($payload['pay_currency'] ?? '')));
    $payAmount = isset($payload['pay_amount']) ? (float) $payload['pay_amount'] : null;
    $actuallyPaid = isset($payload['actually_paid']) ? (float) $payload['actually_paid'] : null;
    $invoiceId = trim((string) ($payload['invoice_id'] ?? ''));
    $payAddress = trim((string) ($payload['pay_address'] ?? $payload['address'] ?? ''));

    if ($orderId === '' && $paymentId === '' && $invoiceId === '') {
        return ['ok' => false, 'message' => 'Missing order_id / payment_id.'];
    }

    $pdo = arowin_db();
    $pdo->beginTransaction();
    try {
        $row = null;
        if ($orderId !== '') {
            $stmt = $pdo->prepare('SELECT * FROM wallet_deposits WHERE order_id = :o LIMIT 1 FOR UPDATE');
            $stmt->execute([':o' => $orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$row && $paymentId !== '') {
            $stmt = $pdo->prepare(
                'SELECT * FROM wallet_deposits WHERE nowpayments_payment_id = :p LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':p' => $paymentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$row && $invoiceId !== '') {
            $stmt = $pdo->prepare(
                'SELECT * FROM wallet_deposits WHERE nowpayments_invoice_id = :i LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':i' => $invoiceId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$row) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Deposit order not found.'];
        }

        if ($paymentId !== '') {
            $dup = $pdo->prepare(
                'SELECT id FROM wallet_deposits
                 WHERE nowpayments_payment_id = :p AND id <> :id
                 LIMIT 1'
            );
            $dup->execute([':p' => $paymentId, ':id' => (int) $row['id']]);
            if ($dup->fetchColumn()) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Duplicate payment_id.'];
            }
        }

        $rawJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $expectedPay = $payAmount;
        if ($expectedPay === null && isset($row['pay_amount']) && $row['pay_amount'] !== null) {
            $expectedPay = (float) $row['pay_amount'];
        }
        $shouldCredit = arowin_deposit_status_is_creditable($status, $actuallyPaid, $expectedPay);
        $storeStatus = $status !== '' ? $status : (string) $row['status'];

        $upd = $pdo->prepare(
            'UPDATE wallet_deposits
             SET status = :s,
                 nowpayments_payment_id = COALESCE(:pid, nowpayments_payment_id),
                 nowpayments_invoice_id = COALESCE(:iid, nowpayments_invoice_id),
                 pay_currency = COALESCE(:pc, pay_currency),
                 pay_amount = COALESCE(:pa, pay_amount),
                 pay_address = COALESCE(:addr, pay_address),
                 actually_paid = :ap,
                 raw_ipn_json = :raw
             WHERE id = :id
             LIMIT 1'
        );
        $upd->execute([
            ':s' => mb_substr($storeStatus, 0, 32),
            ':pid' => $paymentId !== '' ? $paymentId : null,
            ':iid' => $invoiceId !== '' ? $invoiceId : null,
            ':pc' => $payCurrency !== '' ? mb_substr($payCurrency, 0, 32) : null,
            ':pa' => $payAmount,
            ':addr' => $payAddress !== '' ? mb_substr($payAddress, 0, 128) : null,
            ':ap' => $actuallyPaid,
            ':raw' => $rawJson !== false ? $rawJson : null,
            ':id' => (int) $row['id'],
        ]);

        if (!$shouldCredit) {
            // Payment later failed/refunded after we already credited → claw back.
            if (
                arowin_deposit_status_is_failed($status)
                && !empty($row['credited_at'])
                && empty($row['reversed_at'])
            ) {
                $username = arowin_normalize_sponsor_id((string) $row['username']);
                $amount = round((float) $row['amount_usd'], 2);
                if ($username !== '' && $amount >= 1) {
                    $rev = arowin_wallet_debit(
                        $username,
                        $amount,
                        'deposit_reversal',
                        $paymentId !== '' ? $paymentId : $orderId,
                        'NOWPayments deposit reversed (' . $status . ') ' . $orderId
                    );
                    if (!empty($rev['ok'])) {
                        $pdo->prepare(
                            'UPDATE wallet_deposits
                             SET reversed_at = UTC_TIMESTAMP(), status = :s
                             WHERE id = :id AND reversed_at IS NULL
                             LIMIT 1'
                        )->execute([
                            ':s' => mb_substr($status, 0, 32),
                            ':id' => (int) $row['id'],
                        ]);
                        $pdo->commit();
                        return [
                            'ok' => true,
                            'message' => 'Deposit reversed after payment status changed to ' . $status . '.',
                            'credited' => false,
                        ];
                    }
                    // User already spent funds — flag for admin; do not pretend success.
                    $pdo->prepare(
                        'UPDATE wallet_deposits
                         SET note = :n, status = :s
                         WHERE id = :id
                         LIMIT 1'
                    )->execute([
                        ':n' => mb_substr(
                            'REVERSE FAILED (insufficient balance) after NP ' . $status,
                            0,
                            255
                        ),
                        ':s' => mb_substr($status, 0, 32),
                        ':id' => (int) $row['id'],
                    ]);
                    $pdo->commit();
                    error_log(
                        'Deposit reverse failed for ' . $orderId . ': ' . (string) ($rev['message'] ?? '')
                    );
                    return [
                        'ok' => false,
                        'message' => 'Payment failed but wallet reverse needs admin review.',
                        'credited' => false,
                    ];
                }
            }

            $pdo->commit();
            return ['ok' => true, 'message' => 'Status updated: ' . $status, 'credited' => false];
        }

        if (!empty($row['credited_at'])) {
            $pdo->commit();
            return ['ok' => true, 'message' => 'Already credited.', 'credited' => false];
        }

        $username = arowin_normalize_sponsor_id((string) $row['username']);
        $amount = round((float) $row['amount_usd'], 2);
        if ($username === '' || $amount < 1) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Invalid deposit row.'];
        }

        $mark = $pdo->prepare(
            'UPDATE wallet_deposits
             SET credited_at = UTC_TIMESTAMP()
             WHERE id = :id AND credited_at IS NULL
             LIMIT 1'
        );
        $mark->execute([':id' => (int) $row['id']]);
        if ($mark->rowCount() < 1) {
            $pdo->commit();
            return ['ok' => true, 'message' => 'Already credited.', 'credited' => false];
        }

        // Credit inside same transaction for atomicity
        $credit = arowin_wallet_credit(
            $username,
            $amount,
            'deposit',
            $paymentId !== '' ? $paymentId : $orderId,
            'NOWPayments deposit ' . $orderId
        );
        if (empty($credit['ok'])) {
            $pdo->rollBack();
            error_log('Deposit wallet credit failed for ' . $orderId . ': ' . (string) ($credit['message'] ?? ''));
            return ['ok' => false, 'message' => (string) ($credit['message'] ?? 'Wallet credit failed.')];
        }

        $pdo->commit();

        try {
            require_once __DIR__ . '/mail-service.php';
            $bal = isset($credit['balance']) ? (float) $credit['balance'] : null;
            $mail = arowin_notify_deposit_credited(
                $username,
                $amount,
                $paymentId !== '' ? $paymentId : $orderId,
                $bal
            );
            if (empty($mail['sent']) && ($mail['message'] ?? '') !== 'No email on profile.') {
                error_log('Deposit credit email failed for ' . $orderId . ': ' . ($mail['message'] ?? ''));
            }
        } catch (Throwable $mailEx) {
            error_log('Deposit credit email exception for ' . $orderId . ': ' . $mailEx->getMessage());
        }

        return ['ok' => true, 'message' => 'Wallet credited.', 'credited' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Deposit IPN DB error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Database error.'];
    }
}

/**
 * Credit paid deposits when IPN missed (dashboard / checkout load).
 */
function arowin_deposit_catchup_user(string $username): int
{
    static $done = [];
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || isset($done[$username])) {
        return 0;
    }
    $done[$username] = true;

    arowin_ensure_deposits_schema();
    try {
        $stmt = arowin_db()->prepare(
            "SELECT order_id FROM wallet_deposits
             WHERE UPPER(username) = :u
               AND credited_at IS NULL
               AND reversed_at IS NULL
               AND LOWER(status) NOT IN ('failed','expired','refunded')
               AND created_at >= (UTC_TIMESTAMP() - INTERVAL 21 DAY)
             ORDER BY id DESC
             LIMIT 8"
        );
        $stmt->execute([':u' => $username]);
        $orders = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        error_log('Deposit catch-up query failed: ' . $e->getMessage());
        return 0;
    }

    $n = 0;
    foreach ($orders as $oid) {
        try {
            $r = arowin_deposit_refresh_status((string) $oid, $username);
            if (!empty($r['credited'])) {
                $n++;
            }
        } catch (Throwable $e) {
            error_log('Deposit catch-up refresh failed for ' . $oid . ': ' . $e->getMessage());
        }
    }
    return $n;
}
