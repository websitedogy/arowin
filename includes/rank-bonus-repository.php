<?php
/**
 * Rank Bonus Income — separate from Daily Yield
 *
 * - Starts when a rank is achieved (direct L/R active starters)
 * - Pays highest achieved rank’s WEEKLY slab (not daily)
 * - 52 weeks locked schedule → each week unlocks to master wallet
 * - Climbing to a higher rank upgrades the weekly amount (fresh 52 weeks)
 */
declare(strict_types=1);

function arowin_rank_bonus_weeks(): int
{
    return 52;
}

function arowin_rank_bonus_today(): string
{
    return (new DateTimeImmutable('today'))->format('Y-m-d');
}

/**
 * Weekly amount for a rank key from the published schedule.
 */
function arowin_rank_bonus_weekly_for(string $rankKey): float
{
    foreach (arowin_weekly_rank_bonus_table() as $row) {
        if ((string) $row['key'] === $rankKey) {
            return round((float) $row['weekly'], 2);
        }
    }
    return 0.0;
}

function arowin_rank_bonus_total_for(string $rankKey): float
{
    return round(arowin_rank_bonus_weekly_for($rankKey) * arowin_rank_bonus_weeks(), 2);
}

function arowin_rank_bonus_label_for(string $rankKey): string
{
    foreach (arowin_rank_ladder() as $rank) {
        if ((string) $rank['key'] === $rankKey) {
            return (string) $rank['name'];
        }
    }
    return ucwords(str_replace('_', ' ', $rankKey));
}

/**
 * Unlock calendar date for week N (1..52): start + (N-1) weeks.
 * Week 1 unlocks on achievement day.
 */
function arowin_rank_bonus_week_unlock_date(string $startDate, int $weekNo): ?string
{
    $weekNo = max(1, min(arowin_rank_bonus_weeks(), $weekNo));
    try {
        $start = new DateTimeImmutable($startDate);
        return $start->modify('+' . (($weekNo - 1) * 7) . ' days')->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

function arowin_ensure_rank_bonus_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    if (function_exists('arowin_ensure_wallet_schema')) {
        arowin_ensure_wallet_schema();
    }

    $pdo = arowin_db();

    try {
        $typeCol = $pdo->query("SHOW COLUMNS FROM `wallet_transactions` LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
        $typeDef = strtolower((string) ($typeCol['Type'] ?? ''));
        if (strpos($typeDef, 'rank_bonus') === false) {
            $pdo->exec(
                "ALTER TABLE `wallet_transactions`
                 MODIFY COLUMN `type` ENUM(
                   'deposit','transfer_in','transfer_out','withdraw','package_buy',
                   'referral_income','matching_income','deposit_reversal','daily_yield','rank_bonus'
                 ) NOT NULL"
            );
        }
    } catch (Throwable $e) {
        // already migrated / unsupported
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `rank_bonus_enrollments` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `rank_key` VARCHAR(60) NOT NULL,
          `rank_label` VARCHAR(80) NOT NULL,
          `weekly_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
          `daily_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
          `total_target` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
          `start_date` DATE NOT NULL,
          `max_days` INT UNSIGNED NOT NULL DEFAULT 52,
          `days_paid` INT UNSIGNED NOT NULL DEFAULT 0,
          `amount_paid` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
          `status` ENUM('active','stopped','completed') NOT NULL DEFAULT 'active',
          `stop_reason` VARCHAR(80) DEFAULT NULL,
          `stopped_at` TIMESTAMP NULL DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_rb_user_rank` (`username`, `rank_key`),
          KEY `idx_rb_status` (`status`),
          KEY `idx_rb_user` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // max_days / days_paid store WEEKS (52), not calendar days.
    try {
        $pdo->exec(
            "ALTER TABLE `rank_bonus_enrollments`
             MODIFY COLUMN `max_days` INT UNSIGNED NOT NULL DEFAULT 52"
        );
    } catch (Throwable $e) {
        // ignore
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `rank_bonus_payouts` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `enrollment_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
          `rank_key` VARCHAR(60) NOT NULL,
          `rank_label` VARCHAR(80) NOT NULL DEFAULT '',
          `pay_date` DATE NOT NULL,
          `week_no` INT UNSIGNED NOT NULL DEFAULT 1,
          `amount` DECIMAL(14,2) NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_rb_pay_user` (`username`),
          KEY `idx_rb_pay_enroll` (`enrollment_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Upgrade older payouts table (missing weekly columns).
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM `rank_bonus_payouts`')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $colSet = array_flip(array_map('strval', $cols));
        if (!isset($colSet['enrollment_id'])) {
            $pdo->exec(
                'ALTER TABLE `rank_bonus_payouts`
                 ADD COLUMN `enrollment_id` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `username`'
            );
        }
        if (!isset($colSet['rank_key'])) {
            $pdo->exec(
                "ALTER TABLE `rank_bonus_payouts`
                 ADD COLUMN `rank_key` VARCHAR(60) NOT NULL DEFAULT '' AFTER `enrollment_id`"
            );
        }
        if (!isset($colSet['week_no'])) {
            $pdo->exec(
                'ALTER TABLE `rank_bonus_payouts`
                 ADD COLUMN `week_no` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `pay_date`'
            );
        }
        if (!isset($colSet['rank_label'])) {
            $pdo->exec(
                "ALTER TABLE `rank_bonus_payouts`
                 ADD COLUMN `rank_label` VARCHAR(80) NOT NULL DEFAULT '' AFTER `rank_key`"
            );
        } else {
            // Live dump: rank_label NOT NULL without DEFAULT → INSERT 1364.
            $pdo->exec(
                "ALTER TABLE `rank_bonus_payouts`
                 MODIFY COLUMN `rank_label` VARCHAR(80) NOT NULL DEFAULT ''"
            );
        }
    } catch (Throwable $e) {
        error_log('Rank bonus payouts migrate cols: ' . $e->getMessage());
    }

    // Migrate old daily unique key → one payout per week.
    try {
        $idx = $pdo->query("SHOW INDEX FROM `rank_bonus_payouts` WHERE Key_name = 'uq_rb_pay'")->fetch();
        if ($idx) {
            $pdo->exec('ALTER TABLE `rank_bonus_payouts` DROP INDEX `uq_rb_pay`');
        }
    } catch (Throwable $e) {
        // ignore
    }
    try {
        $has = $pdo->query("SHOW INDEX FROM `rank_bonus_payouts` WHERE Key_name = 'uq_rb_pay_week'")->fetch();
        if (!$has) {
            try {
                $pdo->exec(
                    'DELETE p1 FROM `rank_bonus_payouts` p1
                     INNER JOIN `rank_bonus_payouts` p2
                       ON p1.enrollment_id = p2.enrollment_id
                      AND p1.week_no = p2.week_no
                      AND p1.id > p2.id'
                );
            } catch (Throwable $e) {
                error_log('Rank bonus duplicate week cleanup: ' . $e->getMessage());
            }
            $pdo->exec(
                'ALTER TABLE `rank_bonus_payouts`
                 ADD UNIQUE KEY `uq_rb_pay_week` (`enrollment_id`, `week_no`)'
            );
        }
    } catch (Throwable $e) {
        error_log('Rank bonus unique week key: ' . $e->getMessage());
    }
}

/**
 * Enroll for a newly achieved rank. Highest slab wins.
 *
 * @return array{ok:bool, enrolled:bool, message:string}
 */
function arowin_rank_bonus_enroll(string $username, string $rankKey, ?string $startDate = null): array
{
    arowin_ensure_rank_bonus_schema();
    $username = arowin_normalize_sponsor_id($username);
    $rankKey = trim($rankKey);
    if ($username === '' || $rankKey === '') {
        return ['ok' => false, 'enrolled' => false, 'message' => 'Invalid enroll request.'];
    }

    $weekly = arowin_rank_bonus_weekly_for($rankKey);
    if ($weekly <= 0) {
        return ['ok' => false, 'enrolled' => false, 'message' => 'Unknown rank schedule.'];
    }

    $pdo = arowin_db();
    $userStmt = $pdo->prepare(
        'SELECT status FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $userStmt->execute([':u' => $username]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['ok' => false, 'enrolled' => false, 'message' => 'User not found.'];
    }

    $start = $startDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)
        ? $startDate
        : arowin_rank_bonus_today();

    $total = arowin_rank_bonus_total_for($rankKey);
    $label = arowin_rank_bonus_label_for($rankKey);
    $maxWeeks = arowin_rank_bonus_weeks();

    $existing = $pdo->prepare(
        'SELECT id, status FROM rank_bonus_enrollments
         WHERE UPPER(username) = :u AND rank_key = :k LIMIT 1'
    );
    $existing->execute([':u' => $username, ':k' => $rankKey]);
    $cur = $existing->fetch(PDO::FETCH_ASSOC);

    if ($cur) {
        $st = (string) ($cur['status'] ?? '');
        if ($st === 'active' || $st === 'completed') {
            arowin_rank_bonus_stop_other_ranks($username, $rankKey, 'higher_rank');
            return ['ok' => true, 'enrolled' => false, 'message' => 'Already enrolled for this rank.'];
        }
        $pdo->prepare(
            "UPDATE rank_bonus_enrollments
             SET rank_label = :l,
                 weekly_amount = :w,
                 daily_amount = 0,
                 total_target = :t,
                 start_date = :sd,
                 max_days = :m,
                 days_paid = 0,
                 amount_paid = 0,
                 status = 'active',
                 stop_reason = NULL,
                 stopped_at = NULL
             WHERE id = :id
             LIMIT 1"
        )->execute([
            ':l' => $label,
            ':w' => $weekly,
            ':t' => $total,
            ':sd' => $start,
            ':m' => $maxWeeks,
            ':id' => (int) $cur['id'],
        ]);
        arowin_rank_bonus_stop_other_ranks($username, $rankKey, 'higher_rank');
        return ['ok' => true, 'enrolled' => true, 'message' => 'Rank bonus re-enrolled.'];
    }

    try {
        $ins = $pdo->prepare(
            'INSERT INTO rank_bonus_enrollments
                (username, rank_key, rank_label, weekly_amount, daily_amount, total_target,
                 start_date, max_days, days_paid, amount_paid, status)
             VALUES
                (:u, :k, :l, :w, 0, :t, :sd, :m, 0, 0, \'active\')'
        );
        $ins->execute([
            ':u' => $username,
            ':k' => $rankKey,
            ':l' => $label,
            ':w' => $weekly,
            ':t' => $total,
            ':sd' => $start,
            ':m' => $maxWeeks,
        ]);
    } catch (Throwable $e) {
        error_log('Rank bonus enroll error: ' . $e->getMessage());
        return ['ok' => false, 'enrolled' => false, 'message' => 'Enroll failed.'];
    }

    arowin_rank_bonus_stop_other_ranks($username, $rankKey, 'higher_rank');

    return ['ok' => true, 'enrolled' => true, 'message' => 'Rank bonus enrolled.'];
}

/**
 * Stop every active enrollment except the kept rank key (highest slab).
 */
function arowin_rank_bonus_stop_other_ranks(string $username, string $keepRankKey, string $reason = 'higher_rank'): void
{
    arowin_ensure_rank_bonus_schema();
    $username = arowin_normalize_sponsor_id($username);
    $keepRankKey = trim($keepRankKey);
    $reason = mb_substr(trim($reason), 0, 80);
    if ($username === '' || $keepRankKey === '' || $reason === '') {
        return;
    }

    $stmt = arowin_db()->prepare(
        "UPDATE rank_bonus_enrollments
         SET status = 'stopped',
             stop_reason = :r,
             stopped_at = CURRENT_TIMESTAMP
         WHERE UPPER(username) = :u
           AND status = 'active'
           AND rank_key <> :k"
    );
    $stmt->execute([
        ':r' => $reason,
        ':u' => $username,
        ':k' => $keepRankKey,
    ]);
}

/** @deprecated alias */
function arowin_rank_bonus_stop_lower_ranks(string $username, string $keepRankKey): void
{
    arowin_rank_bonus_stop_other_ranks($username, $keepRankKey, 'higher_rank');
}

/**
 * Called whenever ranks are recorded — enroll highest, stop other slabs.
 */
function arowin_rank_bonus_sync_user(string $username): void
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || !function_exists('arowin_user_highest_achieved_rank')) {
        return;
    }

    $highest = arowin_user_highest_achieved_rank($username);
    if ($highest === null) {
        return;
    }

    $rankKey = (string) ($highest['rank_key'] ?? '');
    if ($rankKey === '') {
        return;
    }

    $startDate = null;
    try {
        if (function_exists('arowin_ensure_rank_achievements_schema')) {
            arowin_ensure_rank_achievements_schema();
        }
        $stmt = arowin_db()->prepare(
            'SELECT DATE(achieved_at) FROM rank_achievements
             WHERE UPPER(username) = :u AND rank_key = :k
             LIMIT 1'
        );
        $stmt->execute([':u' => $username, ':k' => $rankKey]);
        $d = $stmt->fetchColumn();
        if ($d !== false && $d !== null && $d !== '') {
            $startDate = (string) $d;
        }
    } catch (Throwable $e) {
        $startDate = null;
    }

    arowin_rank_bonus_enroll($username, $rankKey, $startDate);
}

function arowin_rank_bonus_sum(string $username): float
{
    arowin_ensure_rank_bonus_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return 0.0;
    }
    $stmt = arowin_db()->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions
         WHERE UPPER(username) = :u AND type = 'rank_bonus'"
    );
    $stmt->execute([':u' => $username]);
    return round((float) $stmt->fetchColumn(), 2);
}

/**
 * Unlock due weeks into master wallet for all active enrollments.
 *
 * @return array{ok:bool, paid:float, weeks:int, message:string}
 */
function arowin_rank_bonus_process_user(string $username, ?string $throughDate = null): array
{
    arowin_ensure_rank_bonus_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'paid' => 0.0, 'weeks' => 0, 'message' => 'Invalid user.'];
    }

    $through = $throughDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $throughDate)
        ? $throughDate
        : arowin_rank_bonus_today();

    arowin_rank_bonus_sync_user($username);

    $pdo = arowin_db();
    $stmt = $pdo->prepare(
        "SELECT * FROM rank_bonus_enrollments
         WHERE UPPER(username) = :u AND status = 'active'
         ORDER BY id ASC"
    );
    $stmt->execute([':u' => $username]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($enrollments === []) {
        return ['ok' => true, 'paid' => 0.0, 'weeks' => 0, 'message' => 'Not enrolled.'];
    }

    $paidTotal = 0.0;
    $weeksCredited = 0;

    foreach ($enrollments as $enroll) {
        $r = arowin_rank_bonus_process_enrollment($enroll, $through);
        $paidTotal = round($paidTotal + (float) ($r['paid'] ?? 0), 2);
        $weeksCredited += (int) ($r['weeks'] ?? 0);
    }

    $result = [
        'ok' => true,
        'paid' => $paidTotal,
        'weeks' => $weeksCredited,
        'days' => $weeksCredited, // backward compat for cron counters
        'message' => $weeksCredited > 0
            ? sprintf('Unlocked %d week(s) ($%s).', $weeksCredited, number_format($paidTotal, 2))
            : 'No new weekly unlock due.',
    ];
    return $result;
}

/**
 * Unlock each due week (full weekly amount → wallet). Locked weeks stay locked.
 *
 * @param array<string,mixed> $enroll
 * @return array{ok:bool, paid:float, weeks:int, message:string}
 */
function arowin_rank_bonus_process_enrollment(array $enroll, string $through): array
{
    if (!function_exists('arowin_income_wallet_username') && is_file(__DIR__ . '/income-repository.php')) {
        require_once __DIR__ . '/income-repository.php';
    }
    $pdo = arowin_db();
    $username = arowin_normalize_sponsor_id((string) ($enroll['username'] ?? ''));
    $enrollId = (int) ($enroll['id'] ?? 0);
    $rankKey = (string) ($enroll['rank_key'] ?? '');
    $rankLabel = (string) ($enroll['rank_label'] ?? $rankKey);
    if ($username === '' || $enrollId < 1 || $rankKey === '') {
        return ['ok' => false, 'paid' => 0.0, 'weeks' => 0, 'message' => 'Invalid enrollment.'];
    }

    $weekly = round((float) ($enroll['weekly_amount'] ?? 0), 2);
    if ($weekly <= 0) {
        $weekly = arowin_rank_bonus_weekly_for($rankKey);
    }
    $totalTarget = round((float) ($enroll['total_target'] ?? 0), 2);
    if ($totalTarget <= 0) {
        $totalTarget = arowin_rank_bonus_total_for($rankKey);
    }
    $maxWeeks = max(1, min(arowin_rank_bonus_weeks(), (int) ($enroll['max_days'] ?? arowin_rank_bonus_weeks())));
    // Migrate accidental daily counter (>52) → approximate weeks.
    $weeksPaid = (int) ($enroll['days_paid'] ?? 0);
    if ($weeksPaid > arowin_rank_bonus_weeks()) {
        $weeksPaid = min(arowin_rank_bonus_weeks(), (int) ceil($weeksPaid / 7));
    }
    $amountPaid = round((float) ($enroll['amount_paid'] ?? 0), 2);
    $startDate = (string) ($enroll['start_date'] ?? '');

    try {
        $start = new DateTimeImmutable($startDate);
        $throughDt = new DateTimeImmutable($through);
    } catch (Throwable $e) {
        return ['ok' => false, 'paid' => 0.0, 'weeks' => 0, 'message' => 'Invalid dates.'];
    }

    if ($throughDt < $start) {
        return ['ok' => true, 'paid' => 0.0, 'weeks' => 0, 'message' => 'Not started yet.'];
    }

    $already = [];
    try {
        $paidWeeksStmt = $pdo->prepare(
            'SELECT week_no, SUM(amount) AS amt FROM rank_bonus_payouts
             WHERE enrollment_id = :e
             GROUP BY week_no'
        );
        $paidWeeksStmt->execute([':e' => $enrollId]);
        foreach ($paidWeeksStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $w = (int) ($row['week_no'] ?? 0);
            if ($w > 0) {
                $already[$w] = round((float) ($row['amt'] ?? 0), 2);
            }
        }
    } catch (Throwable $e) {
        error_log('Rank bonus paid-weeks lookup: ' . $e->getMessage());
    }
    // Prefer count of distinct paid weeks if days_paid drifted.
    $weeksPaid = max($weeksPaid, count($already));

    $paidTotal = 0.0;
    $weeksCredited = 0;
    $creditTo = $username;
    if (function_exists('arowin_income_wallet_username')) {
        $walletUser = arowin_income_wallet_username($username);
        if ($walletUser !== '') {
            $creditTo = $walletUser;
        }
    }

    for ($weekNo = 1; $weekNo <= $maxWeeks; $weekNo++) {
        $unlockDate = arowin_rank_bonus_week_unlock_date($startDate, $weekNo);
        if ($unlockDate === null || $unlockDate > $through) {
            break;
        }
        if (isset($already[$weekNo]) && $already[$weekNo] > 0) {
            continue;
        }

        $lockName = 'rbpay_' . $enrollId . '_' . $weekNo;
        $gotLock = false;
        try {
            $lk = $pdo->prepare('SELECT GET_LOCK(:n, 8)');
            $lk->execute([':n' => $lockName]);
            $gotLock = ((int) $lk->fetchColumn() === 1);
        } catch (Throwable $e) {
            $gotLock = false;
        }

        try {
            // Re-check under lock — refresh / double-click must not pay twice.
            $again = $pdo->prepare(
                'SELECT COALESCE(SUM(amount), 0) FROM rank_bonus_payouts
                 WHERE enrollment_id = :e AND week_no = :w'
            );
            $again->execute([':e' => $enrollId, ':w' => $weekNo]);
            $alreadyAmt = round((float) $again->fetchColumn(), 2);
            if ($alreadyAmt > 0) {
                $already[$weekNo] = $alreadyAmt;
                continue;
            }

            try {
                $pdo->prepare(
                    'INSERT INTO rank_bonus_payouts
                        (username, enrollment_id, rank_key, rank_label, pay_date, week_no, amount)
                     VALUES
                        (:u, :e, :k, :l, :d, :w, :a)'
                )->execute([
                    ':u' => $username,
                    ':e' => $enrollId,
                    ':k' => $rankKey,
                    ':l' => $rankLabel !== '' ? $rankLabel : $rankKey,
                    ':d' => $unlockDate,
                    ':w' => $weekNo,
                    ':a' => $weekly,
                ]);
            } catch (Throwable $e) {
                if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                    $already[$weekNo] = $weekly;
                    continue;
                }
                error_log('Rank bonus payout insert failed for ' . $username . ' week ' . $weekNo . ': ' . $e->getMessage());
                continue;
            }

            $note = sprintf(
                'Rank bonus %s · Week %d/%d unlocked (+$%s)',
                $rankLabel,
                $weekNo,
                $maxWeeks,
                number_format($weekly, 2)
            );
            if ($creditTo !== $username) {
                $note .= ' · Power ID ' . $username;
            }

            $credit = arowin_wallet_credit(
                $creditTo,
                $weekly,
                'rank_bonus',
                $creditTo !== $username ? $username : null,
                $note
            );
            if (empty($credit['ok'])) {
                try {
                    $pdo->prepare(
                        'DELETE FROM rank_bonus_payouts
                         WHERE enrollment_id = :e AND week_no = :w
                         LIMIT 1'
                    )->execute([':e' => $enrollId, ':w' => $weekNo]);
                } catch (Throwable $e) {
                    error_log('Rank bonus payout rollback failed: ' . $e->getMessage());
                }
                error_log('Rank bonus week unlock failed for ' . $creditTo . ' week ' . $weekNo . ': ' . ($credit['message'] ?? ''));
                return [
                    'ok' => false,
                    'paid' => $paidTotal,
                    'weeks' => $weeksCredited,
                    'message' => (string) ($credit['message'] ?? 'Credit failed.'),
                ];
            }

            $weeksPaid++;
            $weeksCredited++;
            $amountPaid = round($amountPaid + $weekly, 2);
            $paidTotal = round($paidTotal + $weekly, 2);
            $already[$weekNo] = $weekly;

            $pdo->prepare(
                'UPDATE rank_bonus_enrollments
                 SET days_paid = :n, amount_paid = :a
                 WHERE id = :id
                 LIMIT 1'
            )->execute([
                ':n' => $weeksPaid,
                ':a' => $amountPaid,
                ':id' => $enrollId,
            ]);
        } finally {
            if ($gotLock) {
                try {
                    $rel = $pdo->prepare('SELECT RELEASE_LOCK(:n)');
                    $rel->execute([':n' => $lockName]);
                } catch (Throwable $e) {
                    // ignore
                }
            }
        }
    }

    if ($weeksPaid >= $maxWeeks || $amountPaid >= $totalTarget) {
        $pdo->prepare(
            "UPDATE rank_bonus_enrollments
             SET status = 'completed',
                 stop_reason = 'max_weeks',
                 stopped_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'active'
             LIMIT 1"
        )->execute([':id' => $enrollId]);
    }

    return [
        'ok' => true,
        'paid' => $paidTotal,
        'weeks' => $weeksCredited,
        'message' => $weeksCredited > 0
            ? sprintf('Unlocked %d week(s) ($%s).', $weeksCredited, number_format($paidTotal, 2))
            : 'No new weekly unlock due.',
    ];
}

/**
 * Process all active enrollments (cron).
 *
 * @return array{ok:bool, users:int, paid:float, weeks:int, message:string}
 */
function arowin_rank_bonus_run_all(?string $throughDate = null): array
{
    arowin_ensure_rank_bonus_schema();
    $rows = arowin_db()->query(
        "SELECT DISTINCT username FROM rank_bonus_enrollments WHERE status = 'active' ORDER BY username ASC"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $users = 0;
    $paid = 0.0;
    $weeks = 0;
    foreach ($rows as $u) {
        $r = arowin_rank_bonus_process_user((string) $u, $throughDate);
        if (!empty($r['ok']) && (int) ($r['weeks'] ?? $r['days'] ?? 0) > 0) {
            $users++;
            $paid = round($paid + (float) $r['paid'], 2);
            $weeks += (int) ($r['weeks'] ?? $r['days'] ?? 0);
        }
    }

    return [
        'ok' => true,
        'users' => $users,
        'paid' => $paid,
        'weeks' => $weeks,
        'days' => $weeks,
        'message' => sprintf('Rank bonus: %d user(s), %d week(s), $%s.', $users, $weeks, number_format($paid, 2)),
    ];
}

/**
 * @return array{ok:bool, enrolled:int, message:string}
 */
function arowin_rank_bonus_backfill_enrollments(): array
{
    arowin_ensure_rank_bonus_schema();
    if (function_exists('arowin_ensure_rank_achievements_schema')) {
        arowin_ensure_rank_achievements_schema();
    }

    $rows = arowin_db()->query(
        'SELECT DISTINCT username FROM rank_achievements ORDER BY username ASC'
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $n = 0;
    foreach ($rows as $u) {
        $before = arowin_db()->prepare(
            "SELECT COUNT(*) FROM rank_bonus_enrollments
             WHERE UPPER(username) = :u AND status = 'active'"
        );
        $before->execute([':u' => arowin_normalize_sponsor_id((string) $u)]);
        $had = (int) $before->fetchColumn();

        arowin_rank_bonus_sync_user((string) $u);

        $after = arowin_db()->prepare(
            "SELECT COUNT(*) FROM rank_bonus_enrollments
             WHERE UPPER(username) = :u AND status = 'active'"
        );
        $after->execute([':u' => arowin_normalize_sponsor_id((string) $u)]);
        if ((int) $after->fetchColumn() > $had) {
            $n++;
        }
    }

    return [
        'ok' => true,
        'enrolled' => $n,
        'message' => "Backfilled {$n} rank-bonus enrollment(s).",
    ];
}

/**
 * History: all 52 weeks — Locked until unlock date, then Unlocked → wallet.
 *
 * @return array{
 *   enrollments:list<array<string,mixed>>,
 *   weeks:list<array<string,mixed>>,
 *   next_release:?string,
 *   next_release_fmt:string,
 *   active_rank:string,
 *   total:float
 * }
 */
function arowin_rank_bonus_history(string $username): array
{
    arowin_ensure_rank_bonus_schema();
    $username = arowin_normalize_sponsor_id($username);
    $empty = [
        'enrollments' => [],
        'weeks' => [],
        'next_release' => null,
        'next_release_fmt' => '',
        'active_rank' => '',
        'total' => 0.0,
    ];
    if ($username === '') {
        return $empty;
    }

    // Skip heavy process when user has no ranks / enrollments (empty history).
    try {
        $hasRank = false;
        if (function_exists('arowin_ensure_rank_achievements_schema')) {
            arowin_ensure_rank_achievements_schema();
            $chk = arowin_db()->prepare(
                'SELECT 1 FROM rank_achievements WHERE UPPER(username) = :u LIMIT 1'
            );
            $chk->execute([':u' => $username]);
            $hasRank = (bool) $chk->fetchColumn();
        }
        $enChk = arowin_db()->prepare(
            'SELECT 1 FROM rank_bonus_enrollments WHERE UPPER(username) = :u LIMIT 1'
        );
        $enChk->execute([':u' => $username]);
        $hasEnroll = (bool) $enChk->fetchColumn();

        if (!$hasRank && !$hasEnroll) {
            return $empty;
        }

        arowin_rank_bonus_process_user($username);
    } catch (Throwable $e) {
        error_log('Rank bonus history process: ' . $e->getMessage());
    }

    $pdo = arowin_db();
    $stmt = $pdo->prepare(
        'SELECT * FROM rank_bonus_enrollments
         WHERE UPPER(username) = :u
         ORDER BY id DESC'
    );
    $stmt->execute([':u' => $username]);
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $payStmt = null;
    $payouts = [];
    try {
        $payStmt = $pdo->prepare(
            'SELECT pay_date, week_no, amount, enrollment_id, rank_key
             FROM rank_bonus_payouts
             WHERE UPPER(username) = :u
             ORDER BY week_no ASC, id ASC'
        );
        $payStmt->execute([':u' => $username]);
        $payouts = $payStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Rank bonus history payouts: ' . $e->getMessage());
        $payouts = [];
    }

    $byEnrollWeek = [];
    $total = 0.0;
    foreach ($payouts as $p) {
        $eid = (int) ($p['enrollment_id'] ?? 0);
        $w = (int) ($p['week_no'] ?? 0);
        $amt = round((float) ($p['amount'] ?? 0), 2);
        $total = round($total + $amt, 2);
        if ($w < 1) {
            continue;
        }
        if (!isset($byEnrollWeek[$eid][$w])) {
            $byEnrollWeek[$eid][$w] = [
                'amount' => 0.0,
                'pay_date' => (string) ($p['pay_date'] ?? ''),
            ];
        }
        $byEnrollWeek[$eid][$w]['amount'] = round($byEnrollWeek[$eid][$w]['amount'] + $amt, 2);
    }

    $today = arowin_rank_bonus_today();
    $weeks = [];
    $nextRelease = null;
    $activeRank = '';
    $enrollOut = [];

    foreach ($enrollments as $en) {
        $eid = (int) ($en['id'] ?? 0);
        $rankKey = (string) ($en['rank_key'] ?? '');
        $rankLabel = (string) ($en['rank_label'] ?? $rankKey);
        $weekly = round((float) ($en['weekly_amount'] ?? 0), 2);
        $status = (string) ($en['status'] ?? '');
        $startDate = (string) ($en['start_date'] ?? '');
        $weeksPaid = (int) ($en['days_paid'] ?? 0);
        $maxWeeks = max(1, min(arowin_rank_bonus_weeks(), (int) ($en['max_days'] ?? arowin_rank_bonus_weeks())));
        $amountPaid = round((float) ($en['amount_paid'] ?? 0), 2);

        if ($status === 'active' && $activeRank === '') {
            $activeRank = $rankLabel;
        }

        try {
            $start = new DateTimeImmutable($startDate);
        } catch (Throwable $e) {
            continue;
        }

        $enrollOut[] = [
            'id' => $eid,
            'rank_key' => $rankKey,
            'rank_label' => $rankLabel,
            'weekly' => $weekly,
            'total_target' => round((float) ($en['total_target'] ?? 0), 2),
            'amount_paid' => $amountPaid,
            'weeks_paid' => min($weeksPaid, $maxWeeks),
            'max_weeks' => $maxWeeks,
            'status' => $status,
            'start_date' => $startDate,
            'stop_reason' => (string) ($en['stop_reason'] ?? ''),
        ];

        for ($w = 1; $w <= $maxWeeks; $w++) {
            $unlock = arowin_rank_bonus_week_unlock_date($startDate, $w) ?? '';
            $weekEnd = $start->modify('+' . (($w * 7) - 1) . ' days')->format('Y-m-d');
            $paidInfo = $byEnrollWeek[$eid][$w] ?? null;
            $paidAmt = $paidInfo ? round((float) $paidInfo['amount'], 2) : 0.0;
            $isUnlocked = $paidAmt > 0;

            // Stopped / completed ranks: only keep weeks that already paid to wallet
            if ($status !== 'active' && !$isUnlocked) {
                continue;
            }

            if ($isUnlocked) {
                $wStatus = 'unlocked';
            } elseif ($status === 'stopped' || $status === 'completed') {
                $wStatus = $status === 'completed' && $unlock !== '' && $unlock <= $today
                    ? 'stopped'
                    : ($status === 'stopped' ? 'stopped' : 'locked');
                if ($status === 'stopped') {
                    $wStatus = 'stopped';
                } elseif (!$isUnlocked) {
                    $wStatus = 'locked';
                }
            } elseif ($unlock !== '' && $unlock > $today) {
                $wStatus = 'locked';
            } else {
                // Due but not yet credited (catch-up pending)
                $wStatus = 'locked';
            }

            if ($status === 'active' && !$isUnlocked && $unlock !== '') {
                if ($nextRelease === null) {
                    $nextRelease = $unlock <= $today ? $today : $unlock;
                } elseif ($unlock >= $today && $unlock < $nextRelease) {
                    $nextRelease = $unlock;
                }
            }

            $weeks[] = [
                'enrollment_id' => $eid,
                'rank_key' => $rankKey,
                'rank_label' => $rankLabel,
                'week' => $w,
                'from' => $unlock,
                'to' => $weekEnd,
                'unlock_date' => $unlock,
                'weekly' => $weekly,
                'amount' => $isUnlocked ? $paidAmt : $weekly,
                'status' => $wStatus,
                'label' => sprintf('Week %d / 52 · %s', $w, $rankLabel),
                'amount_fmt' => ($isUnlocked ? '+' : '') . number_format($isUnlocked ? $paidAmt : $weekly, 2) . ' USDT',
                'when' => ($unlock !== '' && $weekEnd !== '')
                    ? (date('d M Y', strtotime($unlock)) . ' – ' . date('d M Y', strtotime($weekEnd)))
                    : '',
                'note' => match ($wStatus) {
                    'unlocked' => 'Unlocked → Wallet · ' . ($unlock !== '' ? date('d M Y', strtotime($unlock)) : ''),
                    'locked' => 'Locked · unlocks ' . ($unlock !== '' ? date('d M Y', strtotime($unlock)) : ''),
                    'stopped' => 'Stopped (higher rank)',
                    default => ucfirst($wStatus),
                },
            ];
        }
    }

    // First locked week unlock date for active schedule.
    if ($nextRelease === null) {
        foreach ($weeks as $wrow) {
            if (($wrow['status'] ?? '') === 'locked' && !empty($wrow['unlock_date'])) {
                $nextRelease = (string) $wrow['unlock_date'];
                break;
            }
        }
    }

    $nextFmt = $nextRelease !== null ? date('d M Y', strtotime($nextRelease)) : '';

    // Unlocked weeks first (top), then locked by week number ascending.
    usort($weeks, static function ($a, $b) {
        $au = (($a['status'] ?? '') === 'unlocked') ? 0 : 1;
        $bu = (($b['status'] ?? '') === 'unlocked') ? 0 : 1;
        if ($au !== $bu) {
            return $au <=> $bu;
        }
        return ((int) ($a['week'] ?? 0)) <=> ((int) ($b['week'] ?? 0));
    });

    return [
        'enrollments' => $enrollOut,
        'weeks' => $weeks,
        'next_release' => $nextRelease,
        'next_release_fmt' => $nextFmt,
        'active_rank' => $activeRank,
        'total' => $total,
    ];
}
