<?php
/**
 * Wallet withdrawals — pending request → admin approve → NOWPayments payout
 */
declare(strict_types=1);

require_once __DIR__ . '/crypto-helpers.php';
require_once __DIR__ . '/wallet-repository.php';

function arowin_ensure_withdrawals_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    arowin_db()->exec(
        "CREATE TABLE IF NOT EXISTS `wallet_withdrawals` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `amount` DECIMAL(14,2) NOT NULL,
          `currency` VARCHAR(32) NOT NULL DEFAULT 'usdtbsc',
          `wallet_address` VARCHAR(128) NOT NULL,
          `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
          `wallet_tx_id` BIGINT UNSIGNED DEFAULT NULL,
          `nowpayments_batch_id` VARCHAR(64) DEFAULT NULL,
          `nowpayments_payout_id` VARCHAR(64) DEFAULT NULL,
          `tx_hash` VARCHAR(128) DEFAULT NULL,
          `admin_note` VARCHAR(255) DEFAULT NULL,
          `reviewed_by` VARCHAR(80) DEFAULT NULL,
          `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
          `raw_create_json` MEDIUMTEXT DEFAULT NULL,
          `raw_ipn_json` MEDIUMTEXT DEFAULT NULL,
          `error_log` TEXT DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_wd_user` (`username`),
          KEY `idx_wd_status` (`status`),
          KEY `idx_wd_batch` (`nowpayments_batch_id`),
          KEY `idx_wd_payout` (`nowpayments_payout_id`),
          KEY `idx_wd_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function arowin_withdrawal_fee_percent(): float
{
    return 10.0;
}

/**
 * Amount sent on-chain after the withdrawal fee.
 * Gross (user request) stays on the wallet_withdrawals.amount / Master Wallet debit.
 */
function arowin_withdrawal_payout_amount(float $gross): float
{
    $gross = round($gross, 2);
    if ($gross <= 0) {
        return 0.0;
    }
    $fee = round($gross * (arowin_withdrawal_fee_percent() / 100), 2);

    return max(0.0, round($gross - $fee, 2));
}

function arowin_withdrawal_ipn_url(): string
{
    // Withdrawal API boots in $AROWIN_API_MODE (no deposit-repository).
    if (!function_exists('arowin_deposit_ipn_url')) {
        require_once __DIR__ . '/deposit-repository.php';
    }

    return arowin_deposit_ipn_url();
}

/**
 * User submits withdrawal — funds debited immediately and held as pending.
 *
 * @return array{ok:bool, message:string, balance?:float, id?:int}
 */
function arowin_withdrawal_request(string $username, float $amount, ?string $walletAddress = null, ?string $currencyOverride = null): array
{
    arowin_ensure_withdrawals_schema();
    arowin_ensure_wallet_schema();
    require_once __DIR__ . '/nowpayments-service.php';

    $username = arowin_normalize_sponsor_id($username);
    $cfg = arowin_nowpayments_config();
    $currency = strtolower(trim((string) ($currencyOverride !== null && trim($currencyOverride) !== ''
        ? $currencyOverride
        : ($cfg['payout_currency'] ?? 'usdtbsc'))));
    $min = (float) ($cfg['min_withdraw'] ?? 10);
    $max = (float) ($cfg['max_withdraw'] ?? 50000);

    $user = arowin_get_user_by_username($username);
    if (!$user) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }
    if (!empty($user['power_owner'])) {
        return ['ok' => false, 'message' => 'Power IDs cannot withdraw. Use your main ID.'];
    }

    $amtCheck = arowin_money_ok($amount, $min, $max, 'Withdrawal');
    if (empty($amtCheck['ok'])) {
        return $amtCheck;
    }
    $amount = (float) $amtCheck['amount'];

    $walletBal = arowin_wallet_balance($username);
    if ($amount > $walletBal + 0.00001) {
        return [
            'ok' => false,
            'message' => 'Limit crossed. You entered $' . number_format($amount, 2)
                . ' but Master Wallet has only $' . number_format($walletBal, 2) . ' USDT.',
            'balance' => $walletBal,
        ];
    }

    $address = trim((string) ($walletAddress !== null && trim($walletAddress) !== ''
        ? $walletAddress
        : ($user['usdt_wallet'] ?? '')));

    $addrCheck = arowin_validate_crypto_address($address, $currency);
    if (empty($addrCheck['ok'])) {
        return [
            'ok' => false,
            'message' => (string) ($addrCheck['message'] ?? 'Invalid wallet address. Update Profile first.'),
        ];
    }

    // Optional live validation via NOWPayments.
    // Local BEP20/TRC20 format check is authoritative; only hard-fail when NP
    // clearly says the address format is invalid. Whitelist / API / auth issues
    // must not block the request here (they surface on payout create).
    $np = arowin_nowpayments();
    if ($np->isConfigured()) {
        $remote = $np->validateAddress($address, $currency);
        if (empty($remote['ok'])) {
            $msg = strtolower((string) ($remote['message'] ?? ''));
            $code = strtoupper((string) ($remote['data']['code'] ?? ''));
            $isBadAddress =
                str_contains($msg, 'invalid payout_address')
                || str_contains($msg, 'invalid address')
                || (
                    $code === 'BAD_CREATE_WITHDRAWAL_REQUEST'
                    && str_contains($msg, 'payout_address')
                );

            if ($isBadAddress) {
                return [
                    'ok' => false,
                    'message' => 'Invalid USDT-BEP20 address. Enter a valid 0x… wallet.',
                ];
            }

            error_log(
                'NOWPayments validate-address soft-skip [' . $currency . ']: '
                . (string) ($remote['message'] ?? 'unknown')
            );
        }
    }

    $pdo = arowin_db();
    try {
        $pdo->beginTransaction();

        $debit = arowin_wallet_debit(
            $username,
            $amount,
            'withdraw',
            null,
            'Withdrawal request to ' . $address
        );
        if (empty($debit['ok'])) {
            $pdo->rollBack();
            return $debit;
        }

        $txStmt = $pdo->prepare(
            "SELECT id FROM wallet_transactions
             WHERE UPPER(username) = :u AND type = 'withdraw'
             ORDER BY id DESC LIMIT 1"
        );
        $txStmt->execute([':u' => $username]);
        $txId = (int) ($txStmt->fetchColumn() ?: 0);

        $ins = $pdo->prepare(
            'INSERT INTO wallet_withdrawals
                (username, amount, currency, wallet_address, status, wallet_tx_id)
             VALUES
                (:u, :a, :c, :w, \'pending\', :tx)'
        );
        $ins->execute([
            ':u' => $username,
            ':a' => $amount,
            ':c' => $currency,
            ':w' => $address,
            ':tx' => $txId > 0 ? $txId : null,
        ]);
        $id = (int) $pdo->lastInsertId();

            $pdo->commit();
            try {
                require_once __DIR__ . '/mail-service.php';
                $mail = arowin_send_transaction_email(
                    $username,
                    'Withdrawal requested',
                    'Your withdrawal request was submitted and is awaiting processing.',
                    [
                        'Amount' => '$' . number_format($amount, 2) . ' USDT',
                        'Network' => strtoupper($currency),
                        'Wallet address' => $address,
                        'Status' => 'Pending review',
                        'New balance' => '$' . number_format((float) ($debit['balance'] ?? 0), 2) . ' USDT',
                    ]
                );
                if (empty($mail['sent']) && ($mail['message'] ?? '') !== 'No email on profile.') {
                    error_log('Withdrawal email failed for ' . $username . ': ' . ($mail['message'] ?? ''));
                }
            } catch (Throwable $mailEx) {
                error_log('Withdrawal email exception for ' . $username . ': ' . $mailEx->getMessage());
            }
            return [
                'ok' => true,
                'id' => $id,
                'balance' => (float) ($debit['balance'] ?? arowin_wallet_balance($username)),
                'message' => 'Withdrawal of $' . number_format($amount, 2) . ' USDT prepared.',
            ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Withdrawal request error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not submit withdrawal. Try again.'];
    }
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_withdrawal_list(string $status = 'pending', int $limit = 100): array
{
    arowin_ensure_withdrawals_schema();
    $limit = max(1, min(500, $limit));
    $status = strtolower(trim($status));

    $sqlBase = "SELECT w.*,
                       nr.full_name AS user_full_name,
                       nr.email AS user_email,
                       nr.phone AS user_phone,
                       nr.wallet_balance AS user_wallet_balance
                FROM wallet_withdrawals w
                LEFT JOIN node_registrations nr ON UPPER(nr.username) = UPPER(w.username)";

    if ($status === 'all') {
        $stmt = arowin_db()->query(
            $sqlBase . " ORDER BY w.id DESC LIMIT {$limit}"
        );
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    $stmt = arowin_db()->prepare(
        $sqlBase . ' WHERE w.status = :s ORDER BY w.id DESC LIMIT ' . $limit
    );
    $stmt->execute([':s' => $status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array<string,int>
 */
function arowin_withdrawal_status_counts(): array
{
    arowin_ensure_withdrawals_schema();
    $counts = [
        'pending' => 0,
        'queued' => 0,
        'awaiting_2fa' => 0,
        'processing' => 0,
        'finished' => 0,
        'failed' => 0,
        'rejected' => 0,
        'refunded' => 0,
        'all' => 0,
    ];
    try {
        $stmt = arowin_db()->query(
            'SELECT status, COUNT(*) AS c FROM wallet_withdrawals GROUP BY status'
        );
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        foreach ($rows as $row) {
            $s = strtolower((string) ($row['status'] ?? ''));
            $n = (int) ($row['c'] ?? 0);
            $counts['all'] += $n;
            if (isset($counts[$s])) {
                $counts[$s] = $n;
            }
        }
    } catch (Throwable $e) {
        error_log('withdrawal status counts: ' . $e->getMessage());
    }
    return $counts;
}

/**
 * Queued (NP low funds) summary for admin dashboard alert.
 *
 * @return array{count:int, amount:float, users:int}
 */
function arowin_withdrawal_queued_summary(): array
{
    arowin_ensure_withdrawals_schema();
    try {
        $stmt = arowin_db()->query(
            "SELECT COUNT(*) AS c,
                    COALESCE(SUM(amount), 0) AS amt,
                    COUNT(DISTINCT username) AS users
             FROM wallet_withdrawals
             WHERE status = 'queued'"
        );
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        return [
            'count' => (int) ($row['c'] ?? 0),
            'amount' => round((float) ($row['amt'] ?? 0), 2),
            'users' => (int) ($row['users'] ?? 0),
        ];
    } catch (Throwable $e) {
        return ['count' => 0, 'amount' => 0.0, 'users' => 0];
    }
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_withdrawal_list_for_user(string $username, int $limit = 50): array
{
    arowin_ensure_withdrawals_schema();
    $username = arowin_normalize_sponsor_id($username);
    $limit = max(1, min(200, $limit));
    $stmt = arowin_db()->prepare(
        "SELECT * FROM wallet_withdrawals WHERE UPPER(username) = :u ORDER BY id DESC LIMIT {$limit}"
    );
    $stmt->execute([':u' => $username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array<string,mixed>|null
 */
function arowin_withdrawal_find(int $id): ?array
{
    arowin_ensure_withdrawals_schema();
    if ($id < 1) {
        return null;
    }
    $stmt = arowin_db()->prepare('SELECT * FROM wallet_withdrawals WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Insert a withdrawal row without debiting Master Wallet.
 *
 * @return array{ok:bool, message:string, id?:int, balance?:float}
 */
function arowin_withdrawal_create_record(
    string $username,
    float $amount,
    string $address,
    string $currency,
    string $status = 'pending'
): array {
    arowin_ensure_withdrawals_schema();
    $username = arowin_normalize_sponsor_id($username);
    $address = trim($address);
    $currency = strtolower(trim($currency));
    $status = strtolower(trim($status));
    if (!in_array($status, ['pending', 'queued'], true)) {
        $status = 'pending';
    }
    if ($username === '' || $address === '' || $amount <= 0) {
        return ['ok' => false, 'message' => 'Invalid withdrawal.'];
    }

    $pdo = arowin_db();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO wallet_withdrawals
                (username, amount, currency, wallet_address, status, wallet_tx_id)
             VALUES
                (:u, :a, :c, :w, :s, NULL)'
        );
        $ins->execute([
            ':u' => $username,
            ':a' => round($amount, 2),
            ':c' => $currency !== '' ? $currency : 'usdtbsc',
            ':w' => $address,
            ':s' => $status,
        ]);
    } catch (Throwable $e) {
        error_log('Withdrawal create record failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not create withdrawal. Try again.'];
    }

    return [
        'ok' => true,
        'id' => (int) $pdo->lastInsertId(),
        'balance' => arowin_wallet_balance($username),
        'message' => 'Withdrawal recorded.',
    ];
}

/**
 * Queue withdrawal when NOWPayments custody balance is low (no Master Wallet debit).
 * Admin can see every queued request under Withdrawals → Queued.
 *
 * @return array{ok:bool, message:string, id?:int, balance?:float, status?:string}
 */
function arowin_withdrawal_queue_for_later(
    string $username,
    float $amount,
    string $address,
    string $currency
): array {
    $created = arowin_withdrawal_create_record($username, $amount, $address, $currency, 'queued');
    if (empty($created['ok'])) {
        return $created;
    }

    $id = (int) ($created['id'] ?? 0);
    $reason = 'NOWPayments custody funds insufficient — queued for admin (process within 12–24h).';
    try {
        arowin_db()->prepare(
            'UPDATE wallet_withdrawals
             SET error_log = :e, admin_note = :n
             WHERE id = :id LIMIT 1'
        )->execute([
            ':e' => $reason,
            ':n' => 'Awaiting NOWPayments top-up / admin payout',
            ':id' => $id,
        ]);
    } catch (Throwable $e) {
        error_log('queue note update: ' . $e->getMessage());
    }

    error_log(
        'WITHDRAWAL QUEUED (NP low funds): #' . $id
        . ' user=' . $username
        . ' amount=' . number_format($amount, 2)
        . ' currency=' . $currency
        . ' address=' . $address
    );

    arowin_withdrawal_notify_admin_queued($id, $username, $amount, $address, $currency);

    return [
        'ok' => true,
        'id' => $id,
        'balance' => (float) ($created['balance'] ?? arowin_wallet_balance($username)),
        'status' => 'queued',
        'message' => 'We will process your amount within 12 hours to 24 hours.',
    ];
}

/**
 * Optional email alert to admin when a withdrawal is queued for low NP funds.
 */
function arowin_withdrawal_notify_admin_queued(
    int $id,
    string $username,
    float $amount,
    string $address,
    string $currency
): void {
    $to = trim((string) env('ADMIN_NOTIFY_EMAIL', env('MAIL_ADMIN', '')));
    if ($to === '' || !function_exists('arowin_send_mail')) {
        return;
    }
    try {
        require_once __DIR__ . '/mail-service.php';
        $user = arowin_get_user_by_username($username);
        $name = trim((string) ($user['full_name'] ?? ''));
        $subject = '[Arowin] Withdrawal queued — NP funds low #' . $id;
        $html = '<p><strong>NOWPayments custody balance is low.</strong></p>'
            . '<p>A user withdrawal was queued for admin processing (12–24h).</p>'
            . '<ul>'
            . '<li>ID: #' . $id . '</li>'
            . '<li>User ID: ' . htmlspecialchars($username) . '</li>'
            . '<li>Name: ' . htmlspecialchars($name !== '' ? $name : '—') . '</li>'
            . '<li>Amount: $' . number_format($amount, 2) . ' ' . strtoupper($currency) . '</li>'
            . '<li>Address: ' . htmlspecialchars($address) . '</li>'
            . '</ul>'
            . '<p>Open Admin → Withdrawals → Queued to review and payout after topping up NOWPayments.</p>';
        arowin_send_mail([
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'text' => strip_tags(str_replace(['</li>', '<br>', '</p>'], ["\n", "\n", "\n"], $html)),
        ]);
    } catch (Throwable $e) {
        error_log('withdrawal admin notify: ' . $e->getMessage());
    }
}

/**
 * Admin rejects a pending withdrawal and refunds the wallet.
 *
 * @return array{ok:bool, message:string}
 */
function arowin_withdrawal_reject(int $id, string $adminUser, string $note = ''): array
{
    arowin_ensure_withdrawals_schema();
    $adminUser = trim($adminUser);
    $note = trim($note);
    $pdo = arowin_db();

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM wallet_withdrawals WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Withdrawal not found.'];
        }
        $status = strtolower((string) $row['status']);
        if ($status !== 'pending') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Only pending withdrawals can be rejected.'];
        }

        $username = arowin_normalize_sponsor_id((string) $row['username']);
        $amount = round((float) $row['amount'], 2);
        $refund = arowin_wallet_credit(
            $username,
            $amount,
            'deposit',
            'WD-REFUND-' . $id,
            'Withdrawal refund #' . $id
        );
        if (empty($refund['ok'])) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Refund failed: ' . (string) ($refund['message'] ?? '')];
        }

        $pdo->prepare(
            "UPDATE wallet_withdrawals
             SET status = 'rejected',
                 reviewed_by = :by,
                 reviewed_at = UTC_TIMESTAMP(),
                 admin_note = :n
             WHERE id = :id
             LIMIT 1"
        )->execute([
            ':by' => mb_substr($adminUser !== '' ? $adminUser : 'admin', 0, 80),
            ':n' => $note !== '' ? mb_substr($note, 0, 255) : 'Rejected by admin',
            ':id' => $id,
        ]);

        $pdo->commit();
        return ['ok' => true, 'message' => 'Withdrawal #' . $id . ' rejected and wallet refunded.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Withdrawal reject error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Reject failed.'];
    }
}

/**
 * Admin approves pending withdrawal and creates NOWPayments payout.
 *
 * @return array{ok:bool, message:string, needs_2fa?:bool, batch_id?:string}
 */
function arowin_withdrawal_approve(int $id, string $adminUser, string $note = '', string $verificationCode = ''): array
{
    arowin_ensure_withdrawals_schema();
    require_once __DIR__ . '/nowpayments-service.php';

    $adminUser = trim($adminUser);
    $note = trim($note);
    $verificationCode = trim($verificationCode);
    $np = arowin_nowpayments();

    if (!$np->hasPayoutCredentials()) {
        return [
            'ok' => false,
            'message' => 'Set NOWPAYMENTS_API_KEY, NOWPAYMENTS_EMAIL, and NOWPAYMENTS_PASSWORD in .env for payouts.',
        ];
    }

    $pdo = arowin_db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT * FROM wallet_withdrawals WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Withdrawal not found.'];
        }

        $status = strtolower((string) $row['status']);

        // Resume 2FA verification if already awaiting
        if ($status === 'awaiting_2fa' && $verificationCode !== '') {
            $batchId = trim((string) ($row['nowpayments_batch_id'] ?? ''));
            $pdo->commit();
            return arowin_withdrawal_verify_2fa($id, $verificationCode, $adminUser);
        }

        if ($status === 'awaiting_2fa' && $verificationCode === '') {
            $pdo->rollBack();
            return [
                'ok' => false,
                'needs_2fa' => true,
                'batch_id' => (string) ($row['nowpayments_batch_id'] ?? ''),
                'message' => 'Enter the NOWPayments 2FA code sent to your email.',
            ];
        }

        if (!in_array($status, ['pending', 'queued'], true)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Withdrawal is not pending (status: ' . $status . ').'];
        }

        $amount = round((float) $row['amount'], 2);
        $address = trim((string) $row['wallet_address']);
        $currency = strtolower((string) ($row['currency'] ?? 'usdtbsc'));
        $username = arowin_normalize_sponsor_id((string) $row['username']);

        // Debit Master Wallet only when sending (queued rows have no debit yet)
        if (empty($row['wallet_tx_id'])) {
            $debit = arowin_wallet_debit(
                $username,
                $amount,
                'withdraw',
                null,
                'Withdrawal #' . $id . ' to ' . $address
            );
            if (empty($debit['ok'])) {
                $pdo->rollBack();
                return [
                    'ok' => false,
                    'message' => (string) ($debit['message'] ?? 'Insufficient wallet balance.'),
                    'balance' => arowin_wallet_balance($username),
                ];
            }
            $txStmt = $pdo->prepare(
                "SELECT id FROM wallet_transactions
                 WHERE UPPER(username) = :u AND type = 'withdraw'
                 ORDER BY id DESC LIMIT 1"
            );
            $txStmt->execute([':u' => $username]);
            $txId = (int) ($txStmt->fetchColumn() ?: 0);
            $pdo->prepare(
                'UPDATE wallet_withdrawals SET wallet_tx_id = :tx WHERE id = :id LIMIT 1'
            )->execute([':tx' => $txId > 0 ? $txId : null, ':id' => $id]);
        }

        $pdo->prepare(
            "UPDATE wallet_withdrawals
             SET status = 'processing',
                 reviewed_by = :by,
                 reviewed_at = UTC_TIMESTAMP(),
                 admin_note = :n
             WHERE id = :id
             LIMIT 1"
        )->execute([
            ':by' => mb_substr($adminUser !== '' ? $adminUser : 'admin', 0, 80),
            ':n' => $note !== '' ? mb_substr($note, 0, 255) : null,
            ':id' => $id,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Withdrawal approve lock error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not lock withdrawal.'];
    }

    // Refresh after possible debit
    $row = arowin_withdrawal_find($id) ?: $row;
    $amount = round((float) $row['amount'], 2);
    $address = trim((string) $row['wallet_address']);
    $currency = strtolower((string) ($row['currency'] ?? 'usdtbsc'));
    $username = arowin_normalize_sponsor_id((string) $row['username']);
    $hadDebit = !empty($row['wallet_tx_id']);
    $payoutAmount = arowin_withdrawal_payout_amount($amount);
    if ($payoutAmount <= 0) {
        if ($hadDebit) {
            arowin_wallet_credit(
                $username,
                $amount,
                'deposit',
                'WD-REFUND-' . $id . '-' . time(),
                'Withdrawal refund #' . $id . ' (payout amount invalid)'
            );
        }
        return ['ok' => false, 'message' => 'Payout amount after fee is too small.'];
    }

    $created = $np->createPayout(
        [[
            'address' => $address,
            'currency' => $currency,
            'amount' => $payoutAmount,
            'ipn_callback_url' => arowin_withdrawal_ipn_url(),
            'fiat_amount' => $payoutAmount,
            'fiat_currency' => 'usd',
        ]],
        arowin_withdrawal_ipn_url()
    );

    $raw = json_encode($created['data'] ?? $created, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (empty($created['ok'])) {
        $err = (string) ($created['message'] ?? 'Payout create failed.');
        $insufficient = ArowinNowPaymentsClient::isInsufficientFundsMessage($err);

        // Restore Master Wallet — never leave funds cut on reject/fail
        if ($hadDebit) {
            arowin_wallet_credit(
                $username,
                $amount,
                'deposit',
                'WD-REFUND-' . $id . '-' . time(),
                'Withdrawal refund #' . $id . ' (payout failed)'
            );
        }

        $newStatus = $insufficient ? 'queued' : 'failed';
        arowin_db()->prepare(
            "UPDATE wallet_withdrawals
             SET status = :s,
                 wallet_tx_id = NULL,
                 error_log = :e,
                 raw_create_json = :r
             WHERE id = :id LIMIT 1"
        )->execute([
            ':s' => $newStatus,
            ':e' => mb_substr($err, 0, 2000),
            ':r' => $raw !== false ? $raw : null,
            ':id' => $id,
        ]);

        if ($insufficient) {
            arowin_withdrawal_notify_admin_queued($id, $username, $amount, $address, $currency);
            return [
                'ok' => true,
                'queued' => true,
                'status' => 'queued',
                'balance' => arowin_wallet_balance($username),
                'message' => 'We will process your amount within 12 hours to 24 hours.',
            ];
        }

        return [
            'ok' => false,
            'status' => 'failed',
            'balance' => arowin_wallet_balance($username),
            'message' => $err,
        ];
    }

    $data = $created['data'] ?? [];
    $batchId = '';
    if (isset($data['id'])) {
        $batchId = (string) $data['id'];
    } elseif (isset($data['batch_withdrawal_id'])) {
        $batchId = (string) $data['batch_withdrawal_id'];
    }

    $payoutId = '';
    $withdrawals = $data['withdrawals'] ?? null;
    if (is_array($withdrawals) && isset($withdrawals[0]) && is_array($withdrawals[0])) {
        $payoutId = (string) ($withdrawals[0]['id'] ?? $withdrawals[0]['withdrawal_id'] ?? '');
    }

    // Only treat as 2FA when NOWPayments actually asks for it.
    // CREATING/WAITING is normal without 2FA — payout continues on their side.
    $needs2fa = false;
    $flat = strtolower(json_encode($data) ?: '');
    if (
        str_contains($flat, 'verification_code')
        || str_contains($flat, '2fa')
        || str_contains($flat, 'two-factor')
        || str_contains($flat, 'two factor')
    ) {
        $needs2fa = true;
    }

    // Auto-OTP from merchant TOTP secret when available
    if ($verificationCode === '') {
        $verificationCode = arowin_nowpayments_totp_code();
    }

    if ($verificationCode !== '' && $batchId !== '') {
        $verified = $np->verifyPayout($batchId, $verificationCode);
        if (!empty($verified['ok'])) {
            $needs2fa = false;
            $rawV = json_encode($verified['data'] ?? $verified, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($rawV !== false) {
                $raw = $rawV;
            }
        } else {
            // If verify failed but API never asked for 2FA, keep processing
            $verifyMsg = strtolower((string) ($verified['message'] ?? ''));
            $verifyLooksRequired = str_contains($verifyMsg, 'verification')
                || str_contains($verifyMsg, '2fa')
                || str_contains($verifyMsg, 'code');
            if ($needs2fa || $verifyLooksRequired) {
                arowin_db()->prepare(
                    "UPDATE wallet_withdrawals
                     SET status = 'awaiting_2fa',
                         nowpayments_batch_id = :b,
                         nowpayments_payout_id = :p,
                         raw_create_json = :r,
                         error_log = :e
                     WHERE id = :id LIMIT 1"
                )->execute([
                    ':b' => $batchId !== '' ? $batchId : null,
                    ':p' => $payoutId !== '' ? $payoutId : null,
                    ':r' => $raw !== false ? $raw : null,
                    ':e' => mb_substr((string) ($verified['message'] ?? '2FA failed'), 0, 2000),
                    ':id' => $id,
                ]);
                return [
                    'ok' => false,
                    'needs_2fa' => true,
                    'batch_id' => $batchId,
                    'message' => 'Payout created but 2FA failed: ' . (string) ($verified['message'] ?? 'invalid code'),
                ];
            }
            error_log('NOWPayments verify soft-skip: ' . (string) ($verified['message'] ?? ''));
        }
    } elseif ($needs2fa && $batchId !== '') {
        arowin_db()->prepare(
            "UPDATE wallet_withdrawals
             SET status = 'awaiting_2fa',
                 nowpayments_batch_id = :b,
                 nowpayments_payout_id = :p,
                 raw_create_json = :r
             WHERE id = :id LIMIT 1"
        )->execute([
            ':b' => $batchId,
            ':p' => $payoutId !== '' ? $payoutId : null,
            ':r' => $raw !== false ? $raw : null,
            ':id' => $id,
        ]);
        return [
            'ok' => false,
            'needs_2fa' => true,
            'batch_id' => $batchId,
            'message' => 'Payout needs NOWPayments 2FA. Set NOWPAYMENTS_2FA_SECRET or disable payout 2FA with NOWPayments.',
        ];
    }

    $newStatus = 'processing';
    arowin_db()->prepare(
        "UPDATE wallet_withdrawals
         SET status = :s,
             nowpayments_batch_id = :b,
             nowpayments_payout_id = :p,
             raw_create_json = :r,
             error_log = NULL
         WHERE id = :id LIMIT 1"
    )->execute([
        ':s' => $newStatus,
        ':b' => $batchId !== '' ? $batchId : null,
        ':p' => $payoutId !== '' ? $payoutId : null,
        ':r' => $raw !== false ? $raw : null,
        ':id' => $id,
    ]);

    return [
        'ok' => true,
        'message' => 'Withdrawal #' . $id . ' sent via NOWPayments. Payout is processing.',
        'batch_id' => $batchId,
    ];
}

/**
 * Confirm payout batch with 2FA code.
 *
 * @return array{ok:bool, message:string, needs_2fa?:bool}
 */
function arowin_withdrawal_verify_2fa(int $id, string $verificationCode, string $adminUser = ''): array
{
    arowin_ensure_withdrawals_schema();
    require_once __DIR__ . '/nowpayments-service.php';

    $verificationCode = trim($verificationCode);
    if ($verificationCode === '') {
        return ['ok' => false, 'needs_2fa' => true, 'message' => 'Verification code is required.'];
    }

    $row = arowin_withdrawal_find($id);
    if (!$row) {
        return ['ok' => false, 'message' => 'Withdrawal not found.'];
    }
    $status = strtolower((string) $row['status']);
    if (!in_array($status, ['awaiting_2fa', 'processing'], true)) {
        return ['ok' => false, 'message' => 'Withdrawal is not awaiting 2FA.'];
    }

    $batchId = trim((string) ($row['nowpayments_batch_id'] ?? ''));
    if ($batchId === '') {
        return ['ok' => false, 'message' => 'Missing NOWPayments batch ID.'];
    }

    $np = arowin_nowpayments();
    $verified = $np->verifyPayout($batchId, $verificationCode);
    $raw = json_encode($verified['data'] ?? $verified, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if (empty($verified['ok'])) {
        arowin_db()->prepare(
            'UPDATE wallet_withdrawals SET error_log = :e, raw_create_json = :r WHERE id = :id LIMIT 1'
        )->execute([
            ':e' => mb_substr((string) ($verified['message'] ?? '2FA failed'), 0, 2000),
            ':r' => $raw !== false ? $raw : null,
            ':id' => $id,
        ]);
        return [
            'ok' => false,
            'needs_2fa' => true,
            'message' => (string) ($verified['message'] ?? '2FA verification failed.'),
        ];
    }

    arowin_db()->prepare(
        "UPDATE wallet_withdrawals
         SET status = 'processing',
             reviewed_by = COALESCE(NULLIF(:by, ''), reviewed_by),
             reviewed_at = UTC_TIMESTAMP(),
             error_log = NULL,
             raw_create_json = :r
         WHERE id = :id LIMIT 1"
    )->execute([
        ':by' => mb_substr(trim($adminUser), 0, 80),
        ':r' => $raw !== false ? $raw : null,
        ':id' => $id,
    ]);

    return ['ok' => true, 'message' => '2FA confirmed. Payout is processing.'];
}

/**
 * Handle payout IPN — update status, store hash, refund on failure.
 *
 * @param array<string,mixed> $payload
 * @return array{ok:bool, message:string}
 */
function arowin_withdrawal_handle_ipn(array $payload): array
{
    arowin_ensure_withdrawals_schema();

    // Payout IPN shapes vary: flat item or nested withdrawals[]
    $items = [];
    if (isset($payload['withdrawals']) && is_array($payload['withdrawals'])) {
        foreach ($payload['withdrawals'] as $w) {
            if (is_array($w)) {
                $items[] = $w;
            }
        }
    } else {
        $items[] = $payload;
    }

    $last = ['ok' => true, 'message' => 'No payout items.'];
    foreach ($items as $item) {
        $last = arowin_withdrawal_apply_ipn_item($item, $payload);
    }
    return $last;
}

/**
 * @param array<string,mixed> $item
 * @param array<string,mixed> $fullPayload
 * @return array{ok:bool, message:string}
 */
function arowin_withdrawal_apply_ipn_item(array $item, array $fullPayload): array
{
    $payoutId = trim((string) ($item['id'] ?? $item['withdrawal_id'] ?? $item['payment_id'] ?? ''));
    $batchId = trim((string) (
        $item['batch_withdrawal_id']
        ?? $fullPayload['id']
        ?? $fullPayload['batch_withdrawal_id']
        ?? ''
    ));
    $status = strtoupper(trim((string) ($item['status'] ?? $fullPayload['status'] ?? '')));
    $hash = trim((string) ($item['hash'] ?? $item['tx_hash'] ?? $fullPayload['hash'] ?? ''));
    $address = trim((string) ($item['address'] ?? ''));
    $amount = isset($item['amount']) ? round((float) $item['amount'], 2) : null;

    if ($payoutId === '' && $batchId === '' && $address === '') {
        return ['ok' => false, 'message' => 'Unrecognized payout IPN.'];
    }

    $pdo = arowin_db();
    $pdo->beginTransaction();
    try {
        $row = null;
        if ($payoutId !== '') {
            $stmt = $pdo->prepare(
                'SELECT * FROM wallet_withdrawals WHERE nowpayments_payout_id = :p LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':p' => $payoutId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$row && $batchId !== '') {
            $stmt = $pdo->prepare(
                'SELECT * FROM wallet_withdrawals WHERE nowpayments_batch_id = :b ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':b' => $batchId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$row && $address !== '' && $amount !== null) {
            $stmt = $pdo->prepare(
                "SELECT * FROM wallet_withdrawals
                 WHERE wallet_address = :a
                   AND status IN ('processing','awaiting_2fa','sending','confirming')
                   AND (
                        amount = :amt
                        OR ROUND(amount * 0.90, 2) = :amt2
                   )
                 ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([':a' => $address, ':amt' => $amount, ':amt2' => $amount]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$row) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Withdrawal row not found for payout IPN.'];
        }

        $id = (int) $row['id'];
        $current = strtolower((string) $row['status']);
        if (in_array($current, ['finished', 'rejected', 'failed', 'refunded'], true)) {
            // Idempotent — already terminal
            $pdo->commit();
            return ['ok' => true, 'message' => 'Already finalized (' . $current . ').'];
        }

        $map = [
            'FINISHED' => 'finished',
            'FAILED' => 'failed',
            'REJECTED' => 'failed',
            'REJECTED_NOT_CHECKED' => 'failed',
            'EXPIRED' => 'failed',
            'PROCESSING' => 'processing',
            'SENDING' => 'sending',
            'WAITING' => 'processing',
            'CREATING' => 'processing',
            'CONFIRMING' => 'confirming',
            'CONFIRMED' => 'confirming',
        ];
        $newStatus = $map[$status] ?? ($status !== '' ? strtolower($status) : $current);

        $rawJson = json_encode($fullPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $pdo->prepare(
            'UPDATE wallet_withdrawals
             SET status = :s,
                 nowpayments_payout_id = COALESCE(:pid, nowpayments_payout_id),
                 nowpayments_batch_id = COALESCE(:bid, nowpayments_batch_id),
                 tx_hash = COALESCE(NULLIF(:h, \'\'), tx_hash),
                 raw_ipn_json = :raw
             WHERE id = :id
             LIMIT 1'
        )->execute([
            ':s' => mb_substr($newStatus, 0, 32),
            ':pid' => $payoutId !== '' ? $payoutId : null,
            ':bid' => $batchId !== '' ? $batchId : null,
            ':h' => $hash,
            ':raw' => $rawJson !== false ? $rawJson : null,
            ':id' => $id,
        ]);

        $failed = in_array($newStatus, ['failed', 'rejected'], true)
            || in_array($status, ['FAILED', 'REJECTED', 'REJECTED_NOT_CHECKED', 'EXPIRED'], true);

        if ($failed && $current !== 'refunded') {
            // Refund once
            $mark = $pdo->prepare(
                "UPDATE wallet_withdrawals SET status = 'refunded'
                 WHERE id = :id AND status <> 'refunded' LIMIT 1"
            );
            $mark->execute([':id' => $id]);
            if ($mark->rowCount() > 0) {
                $refund = arowin_wallet_credit(
                    arowin_normalize_sponsor_id((string) $row['username']),
                    round((float) $row['amount'], 2),
                    'deposit',
                    'WD-FAIL-' . $id,
                    'Withdrawal payout failed refund #' . $id
                );
                if (empty($refund['ok'])) {
                    $pdo->rollBack();
                    return ['ok' => false, 'message' => 'Payout failed but refund error.'];
                }
            }
        }

        $pdo->commit();
        return ['ok' => true, 'message' => 'Payout status: ' . $newStatus];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Withdrawal IPN error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Database error.'];
    }
}

/**
 * Detect if IPN payload is a payout event (vs deposit payment).
 *
 * @param array<string,mixed> $payload
 */
function arowin_nowpayments_is_payout_ipn(array $payload): bool
{
    $orderId = strtoupper(trim((string) ($payload['order_id'] ?? '')));
    if (str_starts_with($orderId, 'DEP-')) {
        return false;
    }
    if (isset($payload['withdrawals']) && is_array($payload['withdrawals'])) {
        return true;
    }
    if (isset($payload['batch_withdrawal_id']) || isset($payload['payout_id'])) {
        return true;
    }
    // Deposit payloads include payment_status and/or payment_id
    if (isset($payload['payment_status']) || isset($payload['payment_id'])) {
        return false;
    }
    $status = strtoupper((string) ($payload['status'] ?? ''));
    $payoutStatuses = [
        'FINISHED', 'FAILED', 'REJECTED', 'PROCESSING', 'SENDING',
        'WAITING', 'CREATING', 'CONFIRMING', 'CONFIRMED', 'REJECTED_NOT_CHECKED',
    ];
    if ($status !== '' && in_array($status, $payoutStatuses, true) && isset($payload['hash'])) {
        return true;
    }
    if (isset($payload['address'], $payload['currency'], $payload['amount']) && !isset($payload['payment_status'])) {
        return true;
    }
    return false;
}
