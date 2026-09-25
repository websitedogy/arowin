<?php
/**
 * Spendable wallet ledger — deposit / transfer / withdraw / package buy
 */
declare(strict_types=1);

function arowin_ensure_wallet_schema(): void
{
    if (!function_exists('arowin_schema_once')) {
        require_once __DIR__ . '/perf.php';
    }
    arowin_schema_once('wallet', 2, static function (): void {
    $pdo = arowin_db();
    $cols = $pdo->query('SHOW COLUMNS FROM node_registrations')->fetchAll(PDO::FETCH_COLUMN);
    $colSet = array_flip(array_map('strval', $cols ?: []));
    if (!isset($colSet['wallet_balance'])) {
        $pdo->exec(
            'ALTER TABLE `node_registrations`
             ADD COLUMN `wallet_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `status`'
        );
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `wallet_transactions` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `type` ENUM('deposit','transfer_in','transfer_out','withdraw','package_buy','referral_income','matching_income') NOT NULL,
          `amount` DECIMAL(14,2) NOT NULL,
          `balance_after` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
          `counterparty` VARCHAR(60) DEFAULT NULL,
          `note` VARCHAR(255) DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_wallet_user_created` (`username`, `created_at`),
          KEY `idx_wallet_type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Avoid running ALTER on every request — DDL implicitly commits and breaks
    // open PDO transactions (transfer / IPN nested debit+credit).
    try {
        $typeCol = $pdo->query("SHOW COLUMNS FROM `wallet_transactions` LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
        $typeDef = strtolower((string) ($typeCol['Type'] ?? ''));
        $needsEnum = strpos($typeDef, 'daily_yield') === false
            || strpos($typeDef, 'deposit_reversal') === false
            || strpos($typeDef, 'rank_bonus') === false;
        if ($needsEnum) {
            $pdo->exec(
                "ALTER TABLE `wallet_transactions`
                 MODIFY COLUMN `type` ENUM(
                   'deposit','transfer_in','transfer_out','withdraw','package_buy',
                   'referral_income','matching_income','deposit_reversal','daily_yield','rank_bonus'
                 ) NOT NULL"
            );
        }
    } catch (Throwable $e) {
        // already migrated / locked
    }
    });
}

function arowin_wallet_balance(string $username): float
{
    arowin_ensure_wallet_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return 0.0;
    }
    $stmt = arowin_db()->prepare(
        'SELECT wallet_balance FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    return round((float) ($stmt->fetchColumn() ?: 0), 2);
}

/**
 * Permanent ledger — rows are never deleted by application code.
 *
 * @return list<array<string, mixed>>
 */
function arowin_wallet_history(string $username, int $limit = 50): array
{
    $result = arowin_wallet_history_query($username, [
        'limit' => $limit,
        'offset' => 0,
    ]);
    return $result['rows'];
}

/**
 * Filtered wallet history (PhonePe-style statement).
 *
 * Filters:
 * - type: all|credit|debit|deposit|transfer_in|transfer_out|withdraw|package_buy|referral_income|matching_income|income
 * - q: search note / counterparty / id
 * - from / to: Y-m-d date range (inclusive)
 *
 * @param array{type?:string,q?:string,from?:string,to?:string,limit?:int,offset?:int} $filters
 * @return array{rows:list<array<string,mixed>>, total:int, credits:float, debits:float}
 */
function arowin_wallet_history_query(string $username, array $filters = []): array
{
    arowin_ensure_wallet_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['rows' => [], 'total' => 0, 'credits' => 0.0, 'debits' => 0.0];
    }

    $type = strtolower(trim((string) ($filters['type'] ?? 'all')));
    $q = trim((string) ($filters['q'] ?? ''));
    $from = trim((string) ($filters['from'] ?? ''));
    $to = trim((string) ($filters['to'] ?? ''));
    $limit = max(1, min(5000, (int) ($filters['limit'] ?? 50)));
    $offset = max(0, (int) ($filters['offset'] ?? 0));

    $where = ['UPPER(username) = :u'];
    $params = [':u' => $username];

    $typeMap = [
        'deposit' => ['deposit'],
        'transfer_in' => ['transfer_in'],
        'transfer_out' => ['transfer_out'],
        'withdraw' => ['withdraw'],
        'package_buy' => ['package_buy'],
        'referral_income' => ['referral_income'],
        'matching_income' => ['matching_income'],
        'daily_yield' => ['daily_yield'],
        'rank_bonus' => ['rank_bonus'],
        'income' => ['referral_income', 'matching_income', 'daily_yield', 'rank_bonus'],
        'earnings' => ['referral_income', 'matching_income', 'daily_yield', 'rank_bonus'],
        'credit' => ['deposit', 'transfer_in', 'referral_income', 'matching_income', 'daily_yield', 'rank_bonus'],
        'debit' => ['transfer_out', 'withdraw', 'package_buy', 'deposit_reversal'],
        'money_added' => ['deposit', 'transfer_in', 'referral_income', 'matching_income', 'daily_yield', 'rank_bonus'],
        'sent' => ['transfer_out'],
        'received' => ['transfer_in'],
        'transfer' => ['transfer_in', 'transfer_out'],
        'packages' => ['package_buy'],
        'withdrawals' => ['withdraw'],
    ];

    if ($type !== '' && $type !== 'all' && isset($typeMap[$type])) {
        $types = $typeMap[$type];
        $parts = [];
        foreach ($types as $i => $t) {
            $key = ':t' . $i;
            $parts[] = $key;
            $params[$key] = $t;
        }
        $where[] = 'type IN (' . implode(',', $parts) . ')';
    }

    if ($q !== '') {
        $where[] = '(note LIKE :q OR counterparty LIKE :q OR CAST(id AS CHAR) LIKE :q OR CAST(amount AS CHAR) LIKE :q)';
        $params[':q'] = '%' . $q . '%';
    }

    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where[] = 'created_at >= :from_dt';
        $params[':from_dt'] = $from . ' 00:00:00';
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where[] = 'created_at <= :to_dt';
        $params[':to_dt'] = $to . ' 23:59:59';
    }

    $sqlWhere = implode(' AND ', $where);
    $pdo = arowin_db();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE {$sqlWhere}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sumStmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type IN ('deposit','transfer_in','referral_income','matching_income','daily_yield','rank_bonus') THEN amount ELSE 0 END), 0) AS credits,
            COALESCE(SUM(CASE WHEN type IN ('transfer_out','withdraw','package_buy','deposit_reversal') THEN amount ELSE 0 END), 0) AS debits
         FROM wallet_transactions
         WHERE {$sqlWhere}"
    );
    $sumStmt->execute($params);
    $sums = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare(
        "SELECT id, username, type, amount, balance_after, counterparty, note, created_at
         FROM wallet_transactions
         WHERE {$sqlWhere}
         ORDER BY id DESC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);

    return [
        'rows' => $stmt->fetchAll() ?: [],
        'total' => $total,
        'credits' => round((float) ($sums['credits'] ?? 0), 2),
        'debits' => round((float) ($sums['debits'] ?? 0), 2),
    ];
}

/**
 * Intentionally unsupported — wallet history is permanent and must never be deleted.
 */
function arowin_wallet_history_delete(int $id): void
{
    throw new RuntimeException('Wallet history cannot be deleted.');
}

/**
 * @return array{ok:bool, message:string, balance?:float}
 */
function arowin_wallet_credit(
    string $username,
    float $amount,
    string $type,
    ?string $counterparty = null,
    string $note = ''
): array {
    arowin_ensure_wallet_schema();
    $username = arowin_normalize_sponsor_id($username);
    $amount = round($amount, 2);
    if ($username === '' || $amount <= 0) {
        return ['ok' => false, 'message' => 'Invalid credit amount.'];
    }
    if (!in_array($type, ['deposit', 'transfer_in', 'referral_income', 'matching_income', 'daily_yield', 'rank_bonus'], true)) {
        return ['ok' => false, 'message' => 'Invalid credit type.'];
    }

    $pdo = arowin_db();
    $ownTx = !$pdo->inTransaction();
    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        $lock = $pdo->prepare(
            'SELECT wallet_balance FROM node_registrations WHERE UPPER(username) = :u LIMIT 1 FOR UPDATE'
        );
        $lock->execute([':u' => $username]);
        $row = $lock->fetch();
        if (!$row) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'message' => 'Account not found.'];
        }
        $balance = round((float) $row['wallet_balance'] + $amount, 2);
        $pdo->prepare(
            'UPDATE node_registrations SET wallet_balance = :b WHERE UPPER(username) = :u LIMIT 1'
        )->execute([':b' => $balance, ':u' => $username]);

        $pdo->prepare(
            'INSERT INTO wallet_transactions
                (username, type, amount, balance_after, counterparty, note)
             VALUES (:u, :t, :a, :b, :c, :n)'
        )->execute([
            ':u' => $username,
            ':t' => $type,
            ':a' => $amount,
            ':b' => $balance,
            ':c' => $counterparty,
            ':n' => $note !== '' ? mb_substr($note, 0, 255) : null,
        ]);

        if ($ownTx) {
            $pdo->commit();
        }
        return ['ok' => true, 'message' => 'Wallet credited.', 'balance' => $balance];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Wallet credit error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not credit wallet.'];
    }
}

/**
 * @return array{ok:bool, message:string, balance?:float}
 */
function arowin_wallet_debit(
    string $username,
    float $amount,
    string $type,
    ?string $counterparty = null,
    string $note = ''
): array {
    arowin_ensure_wallet_schema();
    $username = arowin_normalize_sponsor_id($username);
    $amount = round($amount, 2);
    if ($username === '' || $amount <= 0) {
        return ['ok' => false, 'message' => 'Invalid debit amount.'];
    }
    if (!in_array($type, ['transfer_out', 'withdraw', 'package_buy', 'deposit_reversal'], true)) {
        return ['ok' => false, 'message' => 'Invalid debit type.'];
    }

    $pdo = arowin_db();
    $ownTx = !$pdo->inTransaction();
    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        $lock = $pdo->prepare(
            'SELECT wallet_balance FROM node_registrations WHERE UPPER(username) = :u LIMIT 1 FOR UPDATE'
        );
        $lock->execute([':u' => $username]);
        $row = $lock->fetch();
        if (!$row) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'message' => 'Account not found.'];
        }
        $current = round((float) $row['wallet_balance'], 2);
        if ($current < $amount) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'message' => 'Insufficient wallet balance.'];
        }
        $balance = round($current - $amount, 2);
        $pdo->prepare(
            'UPDATE node_registrations SET wallet_balance = :b WHERE UPPER(username) = :u LIMIT 1'
        )->execute([':b' => $balance, ':u' => $username]);

        $pdo->prepare(
            'INSERT INTO wallet_transactions
                (username, type, amount, balance_after, counterparty, note)
             VALUES (:u, :t, :a, :b, :c, :n)'
        )->execute([
            ':u' => $username,
            ':t' => $type,
            ':a' => $amount,
            ':b' => $balance,
            ':c' => $counterparty,
            ':n' => $note !== '' ? mb_substr($note, 0, 255) : null,
        ]);

        if ($ownTx) {
            $pdo->commit();
        }
        return ['ok' => true, 'message' => 'Wallet debited.', 'balance' => $balance];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Wallet debit error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not debit wallet.'];
    }
}

/**
 * Signed income ledger row (positive credit or negative reversal). History is never deleted.
 * Idempotent on username + type + note.
 *
 * @return array{ok:bool, created:bool, message:string, balance?:float}
 */
function arowin_wallet_income_signed(
    string $username,
    float $amount,
    string $type,
    ?string $counterparty,
    string $note
): array {
    arowin_ensure_wallet_schema();
    $username = arowin_normalize_sponsor_id($username);
    $amount = round($amount, 2);
    $note = trim($note);
    if ($username === '' || $amount == 0.0 || $note === '') {
        return ['ok' => false, 'created' => false, 'message' => 'Invalid signed income amount.'];
    }
    if (!in_array($type, ['referral_income', 'matching_income', 'rank_bonus', 'daily_yield'], true)) {
        return ['ok' => false, 'created' => false, 'message' => 'Invalid income type.'];
    }

    $pdo = arowin_db();
    $ownTx = !$pdo->inTransaction();
    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        $dup = $pdo->prepare(
            'SELECT 1 FROM wallet_transactions
             WHERE UPPER(username) = :u AND type = :t AND note = :n
             LIMIT 1'
        );
        $dup->execute([':u' => $username, ':t' => $type, ':n' => mb_substr($note, 0, 255)]);
        if ($dup->fetchColumn()) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => true, 'created' => false, 'message' => 'Already recorded.'];
        }

        $lock = $pdo->prepare(
            'SELECT wallet_balance FROM node_registrations WHERE UPPER(username) = :u LIMIT 1 FOR UPDATE'
        );
        $lock->execute([':u' => $username]);
        $row = $lock->fetch();
        if (!$row) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'created' => false, 'message' => 'Account not found.'];
        }
        $balance = round((float) $row['wallet_balance'] + $amount, 2);
        $pdo->prepare(
            'UPDATE node_registrations SET wallet_balance = :b WHERE UPPER(username) = :u LIMIT 1'
        )->execute([':b' => $balance, ':u' => $username]);
        $pdo->prepare(
            'INSERT INTO wallet_transactions
                (username, type, amount, balance_after, counterparty, note)
             VALUES (:u, :t, :a, :b, :c, :n)'
        )->execute([
            ':u' => $username,
            ':t' => $type,
            ':a' => $amount,
            ':b' => $balance,
            ':c' => $counterparty,
            ':n' => mb_substr($note, 0, 255),
        ]);
        if ($ownTx) {
            $pdo->commit();
        }
        return ['ok' => true, 'created' => true, 'message' => 'Income ledger updated.', 'balance' => $balance];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Wallet income signed error: ' . $e->getMessage());
        return ['ok' => false, 'created' => false, 'message' => 'Could not update income ledger.'];
    }
}

function arowin_user_has_lifetime_package(string $username): bool
{
    $user = arowin_get_user_by_username($username);
    if (!$user) {
        return false;
    }
    $purchased = arowin_normalize_package((string) ($user['purchased_package'] ?? ''));
    if ($purchased !== '') {
        return true;
    }
    // approved package purchase also counts
    if (function_exists('arowin_ensure_purchases_schema')) {
        arowin_ensure_purchases_schema();
        $stmt = arowin_db()->prepare(
            "SELECT 1 FROM package_purchases
             WHERE UPPER(username) = :u AND status = 'approved'
             LIMIT 1"
        );
        $stmt->execute([':u' => arowin_normalize_sponsor_id($username)]);
        return (bool) $stmt->fetchColumn();
    }
    return false;
}

/**
 * @return array{ok:bool, message:string, balance?:float}
 */
function arowin_wallet_deposit(string $username, float $amount, string $note = ''): array
{
    $username = arowin_normalize_sponsor_id($username);
    $user = arowin_get_user_by_username($username);
    if (!$user) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }
    if (!empty($user['power_owner'])) {
        return ['ok' => false, 'message' => 'Power IDs cannot deposit. Use your main ID.'];
    }
    $amount = round($amount, 2);
    if ($amount < 1) {
        return ['ok' => false, 'message' => 'Minimum deposit is 1 USDT.'];
    }
    if ($amount > 100000) {
        return ['ok' => false, 'message' => 'Deposit amount is too large.'];
    }

    $result = arowin_wallet_credit(
        $username,
        $amount,
        'deposit',
        null,
        $note !== '' ? $note : 'Wallet deposit'
    );
    if (!empty($result['ok'])) {
        $result['message'] = 'Deposit of $' . number_format($amount, 2) . ' USDT added to your wallet.';
        try {
            require_once __DIR__ . '/mail-service.php';
            $bal = isset($result['balance']) ? (float) $result['balance'] : null;
            $mail = arowin_notify_deposit_credited($username, $amount, $note !== '' ? $note : 'Wallet deposit', $bal);
            if (empty($mail['sent']) && ($mail['message'] ?? '') !== 'No email on profile.') {
                error_log('Deposit email failed for ' . $username . ': ' . ($mail['message'] ?? ''));
            }
        } catch (Throwable $mailEx) {
            error_log('Deposit email exception for ' . $username . ': ' . $mailEx->getMessage());
        }
    }
    return $result;
}

/**
 * @return array{ok:bool, message:string}
 */
function arowin_wallet_transfer(string $fromUsername, string $toUsername, float $amount, string $note = ''): array
{
    $fromUsername = arowin_normalize_sponsor_id($fromUsername);
    $toUsername = arowin_normalize_sponsor_id($toUsername);
    $amount = round($amount, 2);

    if ($fromUsername === '' || $toUsername === '') {
        return ['ok' => false, 'message' => 'Enter a valid recipient User ID.'];
    }
    if ($fromUsername === $toUsername) {
        return ['ok' => false, 'message' => 'Cannot transfer to yourself.'];
    }
    if ($amount < 1) {
        return ['ok' => false, 'message' => 'Minimum transfer is 1 USDT.'];
    }

    $from = arowin_get_user_by_username($fromUsername);
    $to = arowin_get_user_by_username($toUsername);
    if (!$from) {
        return ['ok' => false, 'message' => 'Your account was not found.'];
    }
    if (!empty($from['power_owner'])) {
        return ['ok' => false, 'message' => 'Power IDs cannot transfer. Use your main ID.'];
    }
    if (!$to || in_array($toUsername, arowin_root_sponsor_ids(), true)) {
        return ['ok' => false, 'message' => 'Recipient User ID not found.'];
    }

    // Schema DDL must run before beginTransaction (MySQL DDL auto-commits).
    arowin_ensure_wallet_schema();

    $pdo = arowin_db();
    try {
        $pdo->beginTransaction();
        $debit = arowin_wallet_debit(
            $fromUsername,
            $amount,
            'transfer_out',
            $toUsername,
            $note !== '' ? $note : ('Transfer to ' . $toUsername)
        );
        if (empty($debit['ok'])) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return $debit;
        }
        $credit = arowin_wallet_credit(
            $toUsername,
            $amount,
            'transfer_in',
            $fromUsername,
            $note !== '' ? $note : ('Transfer from ' . $fromUsername)
        );
        if (empty($credit['ok'])) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'message' => 'Transfer failed while crediting recipient.'];
        }
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        try {
            require_once __DIR__ . '/mail-service.php';
            foreach ([
                [$fromUsername, 'Transfer sent', 'Your wallet transfer was completed.', [
                    'Amount' => '$' . number_format($amount, 2) . ' USDT',
                    'Recipient' => $toUsername,
                    'Note' => $note !== '' ? $note : '—',
                    'New balance' => '$' . number_format((float) ($debit['balance'] ?? 0), 2) . ' USDT',
                ]],
                [$toUsername, 'Transfer received', 'A wallet transfer has been credited to your account.', [
                    'Amount' => '$' . number_format($amount, 2) . ' USDT',
                    'Sender' => $fromUsername,
                    'Note' => $note !== '' ? $note : '—',
                    'New balance' => '$' . number_format((float) ($credit['balance'] ?? 0), 2) . ' USDT',
                ]],
            ] as $notification) {
                $mail = arowin_send_transaction_email(...$notification);
                if (empty($mail['sent']) && ($mail['message'] ?? '') !== 'No email on profile.') {
                    error_log('Transfer email failed: ' . ($mail['message'] ?? ''));
                }
            }
        } catch (Throwable $mailEx) {
            error_log('Transfer email exception: ' . $mailEx->getMessage());
        }
        return [
            'ok' => true,
            'message' => 'Transferred $' . number_format($amount, 2) . ' USDT to ' . $toUsername . '.',
            'balance' => $debit['balance'] ?? arowin_wallet_balance($fromUsername),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Wallet transfer error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Transfer failed. Try again.'];
    }
}

/**
 * Instant withdrawal via NOWPayments (no admin approval).
 * Debit + payout happen in one flow; queued only if NP custody balance is low.
 *
 * @return array{ok:bool, message:string, balance?:float, id?:int}
 */
function arowin_wallet_withdraw(string $username, float $amount, string $note = ''): array
{
    if (!function_exists('arowin_withdrawal_request')) {
        require_once __DIR__ . '/withdrawal-repository.php';
    }
    // $note reserved for future; address comes from profile
    unset($note);
    return arowin_withdrawal_request($username, $amount);
}

/**
 * Buy package once from wallet balance (lifetime).
 * Wallet is checked first; exact package amount is debited (e.g. $350 → cut 350).
 *
 * @return array{ok:bool, message:string, amount?:float, balance?:float}
 */
function arowin_buy_package_with_wallet(string $username, string $packageCode): array
{
    $username = arowin_normalize_sponsor_id($username);
    $packageCode = arowin_normalize_package($packageCode);
    $info = arowin_package_info($packageCode);
    if (!$info) {
        return ['ok' => false, 'message' => 'Select a valid package.'];
    }

    $user = arowin_get_user_by_username($username);
    if (!$user) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }
    if (!empty($user['power_owner'])) {
        return ['ok' => false, 'message' => 'Power IDs cannot buy packages.'];
    }
    if (arowin_user_has_lifetime_package($username)) {
        return ['ok' => false, 'message' => 'Package already purchased. Lifetime limit is one package.'];
    }

    // Exact catalog price — e.g. package 350 always costs 350.00 USDT
    $amount = round((float) $info['amount'], 2);
    if ($amount < 1) {
        return ['ok' => false, 'message' => 'Invalid package amount.'];
    }

    $balance = arowin_wallet_balance($username);
    if ($balance + 0.00001 < $amount) {
        return [
            'ok' => false,
            'message' => 'Insufficient wallet balance. Need $'
                . number_format($amount, 2)
                . ', available $' . number_format($balance, 2) . '.',
            'balance' => $balance,
        ];
    }

    // Schema work must run outside the purchase transaction (DDL can commit early).
    if (function_exists('arowin_ensure_purchases_schema')) {
        arowin_ensure_purchases_schema();
    }
    arowin_ensure_wallet_schema();

    $pdo = arowin_db();
    $debited = false;
    try {
        $pdo->beginTransaction();

        $debit = arowin_wallet_debit(
            $username,
            $amount,
            'package_buy',
            null,
            'Package ' . $info['label'] . ' ($' . number_format($amount, 2) . ')'
        );
        if (empty($debit['ok'])) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'ok' => false,
                'message' => $debit['message'] ?? 'Could not debit wallet.',
                'balance' => arowin_wallet_balance($username),
            ];
        }
        $debited = true;

        $txRef = 'WALLET-' . $username . '-' . $packageCode . '-' . time();
        $ins = $pdo->prepare(
            "INSERT INTO package_purchases
                (username, package_code, amount_usdt, network, tx_hash, status, admin_note, reviewed_by, reviewed_at)
             VALUES
                (:u, :p, :a, 'WALLET', :h, 'approved', :note, 'wallet', UTC_TIMESTAMP())"
        );
        $ins->bindValue(':u', $username, PDO::PARAM_STR);
        $ins->bindValue(':p', $packageCode, PDO::PARAM_STR);
        $ins->bindValue(':a', (int) $amount, PDO::PARAM_INT);
        $ins->bindValue(':h', $txRef, PDO::PARAM_STR);
        $ins->bindValue(
            ':note',
            'Paid $' . number_format($amount, 2) . ' from wallet',
            PDO::PARAM_STR
        );
        $ins->execute();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Wallet package buy error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not complete package purchase.'];
    }

    $activate = arowin_activate_package_with_power_ids($username, $packageCode);
    if (empty($activate['ok'])) {
        // Undo purchase row + refund exact debit so wallet/lifetime stay correct
        try {
            arowin_db()->prepare(
                "UPDATE package_purchases
                 SET status = 'rejected',
                     admin_note = 'Auto-rejected: activation failed',
                     reviewed_by = 'system',
                     reviewed_at = UTC_TIMESTAMP()
                 WHERE tx_hash = :h AND status = 'approved'
                 LIMIT 1"
            )->execute([':h' => $txRef]);
        } catch (Throwable $e) {
            error_log('Package buy rollback note: ' . $e->getMessage());
        }
        if ($debited) {
            arowin_wallet_credit(
                $username,
                $amount,
                'deposit',
                null,
                'Refund: failed package activation ' . $info['label']
            );
        }
        return [
            'ok' => false,
            'message' => 'Activation failed, wallet refunded: ' . (string) ($activate['message'] ?? 'error'),
            'balance' => arowin_wallet_balance($username),
        ];
    }

    $newBalance = arowin_wallet_balance($username);
    return [
        'ok' => true,
        'amount' => $amount,
        'balance' => $newBalance,
        'message' => 'Paid $' . number_format($amount, 2)
            . ' from wallet for ' . $info['label']
            . '. New balance: $' . number_format($newBalance, 2) . ' USDT. '
            . (string) $activate['message'],
    ];
}

function arowin_fmt_money(float $amount, int $decimals = 2): string
{
    return number_format($amount, $decimals);
}
