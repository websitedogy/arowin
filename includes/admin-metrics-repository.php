<?php
/**
 * Admin dashboard metrics + list queries
 */
declare(strict_types=1);

function arowin_admin_today_bounds(): array
{
    $start = date('Y-m-d 00:00:00');
    $end = date('Y-m-d 23:59:59');
    return [$start, $end];
}

function arowin_ensure_rank_achievements_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    arowin_db()->exec(
        "CREATE TABLE IF NOT EXISTS `rank_achievements` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `rank_key` VARCHAR(60) NOT NULL,
          `rank_label` VARCHAR(80) NOT NULL,
          `left_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `right_count` INT UNSIGNED NOT NULL DEFAULT 0,
          `achieved_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_rank_user_key` (`username`, `rank_key`),
          KEY `idx_rank_achieved` (`achieved_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Record newly achieved ranks for a user (idempotent per rank_key).
 * Power IDs and live IDs use the same rule:
 * Starter = 1 active $50 L + 1 active $50 R.
 * Higher ranks count Starter-qualified IDs on each leg (not raw ID count).
 */
function arowin_record_rank_achievements(string $username): void
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return;
    }
    if (!function_exists('arowin_rank_ladder')) {
        return;
    }
    static $done = [];
    if (isset($done[$username])) {
        return;
    }
    $done[$username] = true;

    if (!function_exists('arowin_rank_metrics')) {
        require_once __DIR__ . '/ranks-repository.php';
    }
    arowin_ensure_rank_achievements_schema();

    try {
        $metrics = arowin_rank_metrics($username);
    } catch (Throwable $e) {
        error_log('Rank metrics failed for ' . $username . ': ' . $e->getMessage());
        return;
    }
    $pdo = arowin_db();

    $eligibleKeys = [];
    $haveByKey = [];
    foreach (arowin_rank_ladder() as $rank) {
        $key = (string) $rank['key'];
        [$haveL, $haveR] = arowin_rank_have_counts($key, $metrics);
        $haveByKey[$key] = [$haveL, $haveR];
        if ($haveL >= (int) $rank['left'] && $haveR >= (int) $rank['right']) {
            $eligibleKeys[] = $key;
        }
    }

    $ins = $pdo->prepare(
        'INSERT INTO rank_achievements
            (username, rank_key, rank_label, left_count, right_count)
         VALUES
            (:u, :k, :l, :left, :right)
         ON DUPLICATE KEY UPDATE
            rank_label = VALUES(rank_label),
            left_count = VALUES(left_count),
            right_count = VALUES(right_count)'
    );
    foreach (arowin_rank_ladder() as $rank) {
        $key = (string) $rank['key'];
        if (!in_array($key, $eligibleKeys, true)) {
            continue;
        }
        $ins->execute([
            ':u' => $username,
            ':k' => $key,
            ':l' => (string) $rank['name'],
            ':left' => (int) ($haveByKey[$key][0] ?? 0),
            ':right' => (int) ($haveByKey[$key][1] ?? 0),
        ]);
    }

    // Drop ranks that were saved under the old "all IDs" count but are not true starters.
    if ($eligibleKeys === []) {
        $del = $pdo->prepare('DELETE FROM rank_achievements WHERE UPPER(username) = :u');
        $del->execute([':u' => $username]);
    } else {
        $placeholders = implode(',', array_fill(0, count($eligibleKeys), '?'));
        $del = $pdo->prepare(
            "DELETE FROM rank_achievements
             WHERE UPPER(username) = ?
               AND rank_key NOT IN ({$placeholders})"
        );
        $del->execute(array_merge([$username], $eligibleKeys));
    }

    if (function_exists('arowin_daily_yield_stop_if_starter')) {
        arowin_daily_yield_stop_if_starter($username);
    } elseif (is_file(__DIR__ . '/daily-yield-repository.php')) {
        require_once __DIR__ . '/daily-yield-repository.php';
        if (function_exists('arowin_daily_yield_stop_if_starter')) {
            arowin_daily_yield_stop_if_starter($username);
        }
    }

    // Rank Bonus starts as soon as a rank is achieved (separate from Daily Yield).
    if (!function_exists('arowin_rank_bonus_sync_user') && is_file(__DIR__ . '/rank-bonus-repository.php')) {
        require_once __DIR__ . '/rank-bonus-repository.php';
    }
    if (function_exists('arowin_rank_bonus_sync_user')) {
        arowin_rank_bonus_sync_user($username);
    }
    if (function_exists('arowin_rank_bonus_process_user')) {
        try {
            arowin_rank_bonus_process_user($username);
        } catch (Throwable $e) {
            error_log('Rank bonus process after achievement failed for ' . $username . ': ' . $e->getMessage());
        }
    }
}

function arowin_admin_wallet_sum_today(string $type): float
{
    if (function_exists('arowin_ensure_wallet_schema')) {
        arowin_ensure_wallet_schema();
    }
    [$start, $end] = arowin_admin_today_bounds();
    $stmt = arowin_db()->prepare(
        'SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions
         WHERE type = :t AND created_at BETWEEN :s AND :e'
    );
    $stmt->execute([':t' => $type, ':s' => $start, ':e' => $end]);
    return round((float) $stmt->fetchColumn(), 2);
}

function arowin_admin_wallet_count_today(string $type): int
{
    if (function_exists('arowin_ensure_wallet_schema')) {
        arowin_ensure_wallet_schema();
    }
    [$start, $end] = arowin_admin_today_bounds();
    $stmt = arowin_db()->prepare(
        'SELECT COUNT(*) FROM wallet_transactions
         WHERE type = :t AND created_at BETWEEN :s AND :e'
    );
    $stmt->execute([':t' => $type, ':s' => $start, ':e' => $end]);
    return (int) $stmt->fetchColumn();
}

/**
 * @return array{
 *   users_all:int, users_pending:int, users_active:int, users_blocked:int,
 *   today_earners:int, today_earners_amount:float,
 *   today_withdrawals:float, today_withdrawals_count:int,
 *   today_deposits:float, today_deposits_count:int,
 *   today_transfers:float, today_transfers_count:int,
 *   today_turnover:float,
 *   total_distribute:float, total_admin:float, total_tds:float, company_surplus:float,
 *   today_failed_payments:int,
 *   support_open:int, guests_all:int, today_rank_achievers:int
 * }
 */
function arowin_admin_dashboard_metrics(): array
{
    if (function_exists('arowin_ensure_wallet_schema')) {
        arowin_ensure_wallet_schema();
    }
    if (function_exists('arowin_ensure_income_schema')) {
        arowin_ensure_income_schema();
    }
    if (function_exists('arowin_ensure_purchases_schema')) {
        arowin_ensure_purchases_schema();
    }
    arowin_ensure_rank_achievements_schema();

    $users = function_exists('arowin_admin_user_status_counts')
        ? arowin_admin_user_status_counts()
        : ['pending' => 0, 'active' => 0, 'inactive' => 0, 'all' => 0];

    [$start, $end] = arowin_admin_today_bounds();
    $pdo = arowin_db();

    $earnersStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT username), COALESCE(SUM(amount), 0)
         FROM income_payouts
         WHERE created_at BETWEEN :s AND :e"
    );
    $earnersStmt->execute([':s' => $start, ':e' => $end]);
    $earnersRow = $earnersStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];

    $todayWithdraw = arowin_admin_wallet_sum_today('withdraw');
    $todayDeposit = arowin_admin_wallet_sum_today('deposit');
    $todayTransferOut = arowin_admin_wallet_sum_today('transfer_out');

    $turnoverStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount_usdt), 0) FROM package_purchases
         WHERE status = 'approved' AND COALESCE(reviewed_at, created_at) BETWEEN :s AND :e"
    );
    $turnoverStmt->execute([':s' => $start, ':e' => $end]);
    $todayTurnover = round((float) $turnoverStmt->fetchColumn(), 2);

    $totalTurnover = (float) $pdo->query(
        "SELECT COALESCE(SUM(amount_usdt), 0) FROM package_purchases WHERE status = 'approved'"
    )->fetchColumn();

    $totalDistribute = (float) $pdo->query(
        'SELECT COALESCE(SUM(amount), 0) FROM income_payouts'
    )->fetchColumn();

    $totalWithdrawAll = (float) $pdo->query(
        "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type = 'withdraw'"
    )->fetchColumn();

    $tdsPct = max(0.0, (float) env('COMPANY_TDS_PERCENT', '0'));
    $adminPct = max(0.0, (float) env('COMPANY_ADMIN_PERCENT', '0'));
    $totalTds = round($totalWithdrawAll * ($tdsPct / 100), 2);
    $totalAdmin = round($totalWithdrawAll * ($adminPct / 100), 2);
    $companySurplus = round(max(0, $totalTurnover - $totalDistribute), 2);

    $failedStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM package_purchases
         WHERE status = 'rejected' AND COALESCE(reviewed_at, created_at) BETWEEN :s AND :e"
    );
    $failedStmt->execute([':s' => $start, ':e' => $end]);
    $failedPurchases = (int) $failedStmt->fetchColumn();

    $failedWallet = 0;
    try {
        $fw = $pdo->prepare(
            "SELECT COUNT(*) FROM wallet_transactions
             WHERE type = 'deposit'
               AND created_at BETWEEN :s AND :e
               AND (note LIKE '%fail%' OR note LIKE '%refund%')"
        );
        $fw->execute([':s' => $start, ':e' => $end]);
        $failedWallet = (int) $fw->fetchColumn();
    } catch (Throwable $e) {
        $failedWallet = 0;
    }

    $supportOpen = 0;
    try {
        if (is_file(dirname(__DIR__) . '/dashboard/support/includes/support.php')) {
            require_once dirname(__DIR__) . '/dashboard/support/includes/support.php';
            if (function_exists('arowin_ensure_support_schema')) {
                arowin_ensure_support_schema();
            }
        }
        $supportOpen = (int) $pdo->query(
            "SELECT COUNT(*) FROM support_threads WHERE status = 'open' AND waiting_on = 'admin'"
        )->fetchColumn();
    } catch (Throwable $e) {
        try {
            $supportOpen = (int) $pdo->query(
                "SELECT COUNT(*) FROM contact_inquiries WHERE status IN ('new','in_progress')"
            )->fetchColumn();
        } catch (Throwable $e2) {
            $supportOpen = 0;
        }
    }

    $guestsAll = 0;
    try {
        if (is_file(dirname(__DIR__) . '/dashboard/guest-list/includes/guests.php')) {
            require_once dirname(__DIR__) . '/dashboard/guest-list/includes/guests.php';
            arowin_ensure_guest_list_schema();
        }
        $guestsAll = (int) $pdo->query('SELECT COUNT(*) FROM guest_list')->fetchColumn();
    } catch (Throwable $e) {
        $guestsAll = 0;
    }

    $rankStmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT username) FROM rank_achievements
         WHERE achieved_at BETWEEN :s AND :e'
    );
    $rankStmt->execute([':s' => $start, ':e' => $end]);
    $todayRanks = (int) $rankStmt->fetchColumn();

    return [
        'users_all' => (int) ($users['all'] ?? 0),
        'users_pending' => (int) ($users['pending'] ?? 0),
        'users_active' => (int) ($users['active'] ?? 0),
        'users_blocked' => (int) ($users['inactive'] ?? 0),
        'today_earners' => (int) ($earnersRow[0] ?? 0),
        'today_earners_amount' => round((float) ($earnersRow[1] ?? 0), 2),
        'today_withdrawals' => $todayWithdraw,
        'today_withdrawals_count' => arowin_admin_wallet_count_today('withdraw'),
        'today_deposits' => $todayDeposit,
        'today_deposits_count' => arowin_admin_wallet_count_today('deposit'),
        'today_transfers' => $todayTransferOut,
        'today_transfers_count' => arowin_admin_wallet_count_today('transfer_out'),
        'today_turnover' => $todayTurnover,
        'total_distribute' => round($totalDistribute, 2),
        'total_admin' => $totalAdmin,
        'total_tds' => $totalTds,
        'company_surplus' => $companySurplus,
        'today_failed_payments' => $failedPurchases + $failedWallet,
        'support_open' => $supportOpen,
        'guests_all' => $guestsAll,
        'today_rank_achievers' => $todayRanks,
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_admin_list_wallet_tx(array $types, bool $todayOnly = false, int $limit = 200): array
{
    if (function_exists('arowin_ensure_wallet_schema')) {
        arowin_ensure_wallet_schema();
    }
    $types = array_values(array_filter($types, static fn($t) => is_string($t) && $t !== ''));
    if ($types === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($types as $i => $type) {
        $key = ':t' . $i;
        $placeholders[] = $key;
        $params[$key] = $type;
    }

    $sql = 'SELECT id, username, type, amount, balance_after, counterparty, note, created_at
            FROM wallet_transactions
            WHERE type IN (' . implode(',', $placeholders) . ')';
    if ($todayOnly) {
        [$start, $end] = arowin_admin_today_bounds();
        $sql .= ' AND created_at BETWEEN :s AND :e';
        $params[':s'] = $start;
        $params[':e'] = $end;
    }
    $limit = max(1, min(500, $limit));
    $sql .= " ORDER BY id DESC LIMIT {$limit}";

    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_admin_list_today_earners(int $limit = 200): array
{
    if (function_exists('arowin_ensure_income_schema')) {
        arowin_ensure_income_schema();
    }
    [$start, $end] = arowin_admin_today_bounds();
    $limit = max(1, min(500, $limit));
    $stmt = arowin_db()->prepare(
        "SELECT username,
                COUNT(*) AS payouts,
                COALESCE(SUM(amount), 0) AS total_amount,
                MAX(created_at) AS last_at
         FROM income_payouts
         WHERE created_at BETWEEN :s AND :e
         GROUP BY username
         ORDER BY total_amount DESC
         LIMIT {$limit}"
    );
    $stmt->execute([':s' => $start, ':e' => $end]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_admin_list_income_payouts(int $limit = 200): array
{
    if (function_exists('arowin_ensure_income_schema')) {
        arowin_ensure_income_schema();
    }
    $limit = max(1, min(500, $limit));
    $stmt = arowin_db()->query(
        "SELECT id, username, type, amount, source_username, package_code, pairs, note, created_at
         FROM income_payouts
         ORDER BY id DESC
         LIMIT {$limit}"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_admin_list_turnover(bool $todayOnly = false, int $limit = 200): array
{
    if (function_exists('arowin_ensure_purchases_schema')) {
        arowin_ensure_purchases_schema();
    }
    $sql = "SELECT id, username, package_code, amount_usdt, tx_hash, status, reviewed_by, reviewed_at, created_at
            FROM package_purchases
            WHERE status = 'approved'";
    $params = [];
    if ($todayOnly) {
        [$start, $end] = arowin_admin_today_bounds();
        $sql .= ' AND COALESCE(reviewed_at, created_at) BETWEEN :s AND :e';
        $params[':s'] = $start;
        $params[':e'] = $end;
    }
    $limit = max(1, min(500, $limit));
    $sql .= " ORDER BY COALESCE(reviewed_at, created_at) DESC LIMIT {$limit}";
    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_admin_list_failed_payments(bool $todayOnly = true, int $limit = 200): array
{
    if (function_exists('arowin_ensure_purchases_schema')) {
        arowin_ensure_purchases_schema();
    }
    $sql = "SELECT id, username, package_code, amount_usdt, tx_hash, status, admin_note, reviewed_at, created_at
            FROM package_purchases
            WHERE status = 'rejected'";
    $params = [];
    if ($todayOnly) {
        [$start, $end] = arowin_admin_today_bounds();
        $sql .= ' AND COALESCE(reviewed_at, created_at) BETWEEN :s AND :e';
        $params[':s'] = $start;
        $params[':e'] = $end;
    }
    $limit = max(1, min(500, $limit));
    $sql .= " ORDER BY id DESC LIMIT {$limit}";
    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return list<array<string,mixed>>
 */
/**
 * Legacy flat ticket list (prefer arowin_support_admin_inbox for the desk UI).
 *
 * @return list<array<string,mixed>>
 */
function arowin_admin_list_support(int $limit = 200): array
{
    $limit = max(1, min(500, $limit));
    try {
        if (is_file(dirname(__DIR__) . '/dashboard/support/includes/support.php')) {
            require_once dirname(__DIR__) . '/dashboard/support/includes/support.php';
            if (function_exists('arowin_ensure_support_schema')) {
                arowin_ensure_support_schema();
            }
            if (function_exists('arowin_support_admin_inbox')) {
                return arowin_support_admin_inbox('all', '', $limit);
            }
        }
        $stmt = arowin_db()->query(
            "SELECT id, first_name, last_name, email, phone, organization, topic, subject, message,
                    status, admin_reply, admin_replied_at, created_at
             FROM contact_inquiries
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_admin_list_guests(int $limit = 300): array
{
    try {
        if (is_file(dirname(__DIR__) . '/dashboard/guest-list/includes/guests.php')) {
            require_once dirname(__DIR__) . '/dashboard/guest-list/includes/guests.php';
            arowin_ensure_guest_list_schema();
        }
        $limit = max(1, min(500, $limit));
        $stmt = arowin_db()->query(
            "SELECT id, owner_username, full_name, phone, profession, relation_type, state, district, status, created_at
             FROM guest_list
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_admin_list_rank_achievers(bool $todayOnly = true, int $limit = 200): array
{
    arowin_ensure_rank_achievements_schema();
    $sql = 'SELECT id, username, rank_key, rank_label, left_count, right_count, achieved_at
            FROM rank_achievements';
    $params = [];
    if ($todayOnly) {
        [$start, $end] = arowin_admin_today_bounds();
        $sql .= ' WHERE achieved_at BETWEEN :s AND :e';
        $params[':s'] = $start;
        $params[':e'] = $end;
    }
    $limit = max(1, min(500, $limit));
    $sql .= " ORDER BY achieved_at DESC LIMIT {$limit}";
    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function arowin_admin_fmt_money(float $amount): string
{
    return '$' . number_format($amount, 2);
}
