<?php
/**
 * Pure $50 package daily yield
 *
 * - Only purchased_package = 50 (no $150/$350 splits)
 * - $0.25 / day for up to 400 days
 * - Stops immediately when Starter rank is achieved
 * - Also stops on upgrade to a higher package
 */
declare(strict_types=1);

function arowin_daily_yield_amount(): float
{
    return 0.25;
}

function arowin_daily_yield_max_days(): int
{
    return 400;
}

function arowin_ensure_daily_yield_schema(): void
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
        $pdo->exec(
            "ALTER TABLE `wallet_transactions`
             MODIFY COLUMN `type` ENUM(
               'deposit','transfer_in','transfer_out','withdraw','package_buy',
               'referral_income','matching_income','deposit_reversal','daily_yield','rank_bonus'
             ) NOT NULL"
        );
    } catch (Throwable $e) {
        // already migrated / unsupported
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `daily_yield_enrollments` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `start_date` DATE NOT NULL,
          `daily_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.25,
          `max_days` INT UNSIGNED NOT NULL DEFAULT 400,
          `days_paid` INT UNSIGNED NOT NULL DEFAULT 0,
          `status` ENUM('active','stopped','completed') NOT NULL DEFAULT 'active',
          `stop_reason` VARCHAR(80) DEFAULT NULL,
          `stopped_at` TIMESTAMP NULL DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_dy_user` (`username`),
          KEY `idx_dy_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `daily_yield_payouts` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `pay_date` DATE NOT NULL,
          `amount` DECIMAL(14,2) NOT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_dy_pay` (`username`, `pay_date`),
          KEY `idx_dy_user` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function arowin_daily_yield_today(): string
{
    return (new DateTimeImmutable('today'))->format('Y-m-d');
}

/**
 * True only for a pure $50 purchase (not split packages).
 */
function arowin_daily_yield_is_pure_fifty(string $packageCode): bool
{
    return arowin_normalize_package($packageCode) === '50';
}

function arowin_daily_yield_has_starter(string $username): bool
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return false;
    }
    // Do not call arowin_record_rank_achievements here — that hooks stop_if_starter.
    $keys = function_exists('arowin_user_achieved_rank_keys')
        ? arowin_user_achieved_rank_keys($username)
        : [];
    return isset($keys['starter']);
}

/**
 * First calendar day Starter was recorded (Y-m-d), or null.
 */
function arowin_daily_yield_starter_date(string $username): ?string
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return null;
    }
    if (function_exists('arowin_ensure_rank_achievements_schema')) {
        arowin_ensure_rank_achievements_schema();
    }
    try {
        $stmt = arowin_db()->prepare(
            'SELECT DATE(achieved_at) FROM rank_achievements
             WHERE UPPER(username) = :u AND rank_key = \'starter\'
             LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $d = $stmt->fetchColumn();
        if ($d === false || $d === null || $d === '') {
            return null;
        }
        return (string) $d;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Enroll after pure $50 activation. No-op for higher packages / power IDs / Starter already.
 *
 * @return array{ok:bool, enrolled:bool, message:string}
 */
function arowin_daily_yield_enroll(string $username, ?string $startDate = null): array
{
    arowin_ensure_daily_yield_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'enrolled' => false, 'message' => 'Invalid user.'];
    }

    $pdo = arowin_db();
    $stmt = $pdo->prepare(
        "SELECT purchased_package, power_owner, status
         FROM node_registrations WHERE UPPER(username) = :u LIMIT 1"
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'enrolled' => false, 'message' => 'User not found.'];
    }
    if (!empty($row['power_owner'])) {
        return ['ok' => true, 'enrolled' => false, 'message' => 'Power IDs are not eligible.'];
    }
    if (!arowin_daily_yield_is_pure_fifty((string) ($row['purchased_package'] ?? ''))) {
        return ['ok' => true, 'enrolled' => false, 'message' => 'Only pure $50 package is eligible.'];
    }

    if (function_exists('arowin_record_rank_achievements')) {
        arowin_record_rank_achievements($username);
    }
    if (arowin_daily_yield_has_starter($username)) {
        return ['ok' => true, 'enrolled' => false, 'message' => 'Already Starter — yield not started.'];
    }

    $start = $startDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)
        ? $startDate
        : arowin_daily_yield_today();

    $existing = $pdo->prepare(
        'SELECT id, status, stop_reason FROM daily_yield_enrollments WHERE UPPER(username) = :u LIMIT 1'
    );
    $existing->execute([':u' => $username]);
    $cur = $existing->fetch(PDO::FETCH_ASSOC);
    if ($cur) {
        $st = (string) ($cur['status'] ?? '');
        if ($st === 'completed' || ($st === 'stopped' && (string) ($cur['stop_reason'] ?? '') === 'starter_rank')) {
            return ['ok' => true, 'enrolled' => false, 'message' => 'Enrollment already closed.'];
        }
        if ($st === 'active') {
            return ['ok' => true, 'enrolled' => false, 'message' => 'Already enrolled.'];
        }
        // Reactivate only if previously stopped for upgrade and buying $50 again (rare).
        $pdo->prepare(
            "UPDATE daily_yield_enrollments
             SET start_date = :d,
                 daily_amount = :a,
                 max_days = :m,
                 days_paid = 0,
                 status = 'active',
                 stop_reason = NULL,
                 stopped_at = NULL
             WHERE UPPER(username) = :u
             LIMIT 1"
        )->execute([
            ':d' => $start,
            ':a' => arowin_daily_yield_amount(),
            ':m' => arowin_daily_yield_max_days(),
            ':u' => $username,
        ]);
        return ['ok' => true, 'enrolled' => true, 'message' => 'Daily yield re-enrolled.'];
    }

    try {
        $ins = $pdo->prepare(
            'INSERT INTO daily_yield_enrollments
                (username, start_date, daily_amount, max_days, days_paid, status)
             VALUES
                (:u, :d, :a, :m, 0, \'active\')'
        );
        $ins->execute([
            ':u' => $username,
            ':d' => $start,
            ':a' => arowin_daily_yield_amount(),
            ':m' => arowin_daily_yield_max_days(),
        ]);
    } catch (Throwable $e) {
        error_log('Daily yield enroll error: ' . $e->getMessage());
        return ['ok' => false, 'enrolled' => false, 'message' => 'Enroll failed.'];
    }

    return ['ok' => true, 'enrolled' => true, 'message' => 'Daily yield enrolled.'];
}

/**
 * @return array{ok:bool, stopped:bool, message:string}
 */
function arowin_daily_yield_stop(string $username, string $reason): array
{
    arowin_ensure_daily_yield_schema();
    $username = arowin_normalize_sponsor_id($username);
    $reason = mb_substr(trim($reason), 0, 80);
    if ($username === '' || $reason === '') {
        return ['ok' => false, 'stopped' => false, 'message' => 'Invalid stop request.'];
    }

    $stmt = arowin_db()->prepare(
        "UPDATE daily_yield_enrollments
         SET status = 'stopped',
             stop_reason = :r,
             stopped_at = CURRENT_TIMESTAMP
         WHERE UPPER(username) = :u AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute([':r' => $reason, ':u' => $username]);
    $n = $stmt->rowCount();

    return [
        'ok' => true,
        'stopped' => $n > 0,
        'message' => $n > 0 ? 'Daily yield stopped.' : 'No active enrollment.',
    ];
}

function arowin_daily_yield_stop_if_starter(string $username): void
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return;
    }
    arowin_ensure_daily_yield_schema();

    $chk = arowin_db()->prepare(
        "SELECT id FROM daily_yield_enrollments
         WHERE UPPER(username) = :u AND status = 'active' LIMIT 1"
    );
    $chk->execute([':u' => $username]);
    if (!$chk->fetchColumn()) {
        return;
    }

    // Avoid recursion: check keys without re-recording ranks here when called FROM record.
    $keys = function_exists('arowin_user_achieved_rank_keys')
        ? arowin_user_achieved_rank_keys($username)
        : [];
    if (isset($keys['starter'])) {
        arowin_daily_yield_stop($username, 'starter_rank');
    }
}

function arowin_daily_yield_stop_if_upgraded(string $username, string $packageCode): void
{
    if (arowin_daily_yield_is_pure_fifty($packageCode)) {
        return;
    }
    arowin_daily_yield_stop($username, 'upgraded_package');
}

function arowin_daily_yield_sum(string $username): float
{
    arowin_ensure_daily_yield_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return 0.0;
    }
    $stmt = arowin_db()->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions
         WHERE UPPER(username) = :u AND type = 'daily_yield'"
    );
    $stmt->execute([':u' => $username]);
    return round((float) $stmt->fetchColumn(), 2);
}

/**
 * Credit missed days up to $throughDate (inclusive). Stops on Starter / max days.
 *
 * @return array{ok:bool, paid:float, days:int, message:string}
 */
function arowin_daily_yield_process_user(string $username, ?string $throughDate = null): array
{
    arowin_ensure_daily_yield_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'paid' => 0.0, 'days' => 0, 'message' => 'Invalid user.'];
    }

    $through = $throughDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $throughDate)
        ? $throughDate
        : arowin_daily_yield_today();

    $pdo = arowin_db();
    $stmt = $pdo->prepare(
        'SELECT * FROM daily_yield_enrollments WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $enroll = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$enroll) {
        return ['ok' => true, 'paid' => 0.0, 'days' => 0, 'message' => 'Not enrolled.'];
    }
    if ((string) $enroll['status'] !== 'active') {
        return ['ok' => true, 'paid' => 0.0, 'days' => 0, 'message' => 'Enrollment not active.'];
    }

    // Confirm still pure $50
    $userStmt = $pdo->prepare(
        'SELECT purchased_package, power_owner FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $userStmt->execute([':u' => $username]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!empty($user['power_owner']) || !arowin_daily_yield_is_pure_fifty((string) ($user['purchased_package'] ?? ''))) {
        arowin_daily_yield_stop($username, 'upgraded_package');
        return ['ok' => true, 'paid' => 0.0, 'days' => 0, 'message' => 'Stopped — not pure $50.'];
    }

    if (function_exists('arowin_record_rank_achievements')) {
        arowin_record_rank_achievements($username);
    }
    $starterDate = arowin_daily_yield_starter_date($username);
    // Catch-up still credits days strictly before Starter day; then status stops.

    $daily = round((float) $enroll['daily_amount'], 2);
    if ($daily <= 0) {
        $daily = arowin_daily_yield_amount();
    }
    $maxDays = max(1, (int) $enroll['max_days']);
    $daysPaid = (int) $enroll['days_paid'];
    $startDate = (string) $enroll['start_date'];

    $paidTotal = 0.0;
    $daysCredited = 0;

    try {
        $start = new DateTimeImmutable($startDate);
        $end = new DateTimeImmutable($through);
        $lastAllowed = $start->modify('+' . ($maxDays - 1) . ' days');
        if ($end > $lastAllowed) {
            $end = $lastAllowed;
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'paid' => 0.0, 'days' => 0, 'message' => 'Invalid dates.'];
    }

    if ($end < $start) {
        return ['ok' => true, 'paid' => 0.0, 'days' => 0, 'message' => 'Not started yet.'];
    }

    $paidDatesStmt = $pdo->prepare(
        'SELECT pay_date FROM daily_yield_payouts WHERE UPPER(username) = :u'
    );
    $paidDatesStmt->execute([':u' => $username]);
    $already = [];
    foreach ($paidDatesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $d) {
        $already[(string) $d] = true;
    }

    $cursor = $start;
    while ($cursor <= $end) {
        if ($daysPaid >= $maxDays) {
            break;
        }
        $day = $cursor->format('Y-m-d');

        // Stop from the Starter achievement day onward (no credit that day or after).
        if ($starterDate !== null && $day >= $starterDate) {
            break;
        }

        if (!isset($already[$day])) {
            $credit = arowin_wallet_credit(
                $username,
                $daily,
                'daily_yield',
                null,
                'Daily yield day ' . ($daysPaid + 1) . '/' . $maxDays . ' ($50 package)'
            );
            if (empty($credit['ok'])) {
                error_log('Daily yield credit failed for ' . $username . ' @ ' . $day . ': ' . ($credit['message'] ?? ''));
                return [
                    'ok' => false,
                    'paid' => $paidTotal,
                    'days' => $daysCredited,
                    'message' => (string) ($credit['message'] ?? 'Credit failed.'),
                ];
            }

            try {
                $pdo->prepare(
                    'INSERT INTO daily_yield_payouts (username, pay_date, amount)
                     VALUES (:u, :d, :a)'
                )->execute([':u' => $username, ':d' => $day, ':a' => $daily]);
            } catch (Throwable $e) {
                // Unique race — treat as already paid
            }

            $daysPaid++;
            $daysCredited++;
            $paidTotal = round($paidTotal + $daily, 2);
            $already[$day] = true;

            $pdo->prepare(
                'UPDATE daily_yield_enrollments SET days_paid = :n WHERE UPPER(username) = :u LIMIT 1'
            )->execute([':n' => $daysPaid, ':u' => $username]);
        }

        $cursor = $cursor->modify('+1 day');
    }

    if ($starterDate !== null) {
        arowin_daily_yield_stop($username, 'starter_rank');
    } elseif ($daysPaid >= $maxDays) {
        $pdo->prepare(
            "UPDATE daily_yield_enrollments
             SET status = 'completed',
                 stop_reason = 'max_days',
                 stopped_at = CURRENT_TIMESTAMP
             WHERE UPPER(username) = :u AND status = 'active'
             LIMIT 1"
        )->execute([':u' => $username]);
    }

    return [
        'ok' => true,
        'paid' => $paidTotal,
        'days' => $daysCredited,
        'message' => $daysCredited > 0
            ? sprintf('Credited %d day(s) ($%s).', $daysCredited, number_format($paidTotal, 2))
            : 'No new yield due.',
    ];
}

/**
 * Process all active enrollments (cron).
 *
 * @return array{ok:bool, users:int, paid:float, days:int, message:string}
 */
function arowin_daily_yield_run_all(?string $throughDate = null): array
{
    arowin_ensure_daily_yield_schema();
    $rows = arowin_db()->query(
        "SELECT username FROM daily_yield_enrollments WHERE status = 'active' ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $users = 0;
    $paid = 0.0;
    $days = 0;
    foreach ($rows as $u) {
        $r = arowin_daily_yield_process_user((string) $u, $throughDate);
        if (!empty($r['ok']) && (int) ($r['days'] ?? 0) > 0) {
            $users++;
            $paid = round($paid + (float) $r['paid'], 2);
            $days += (int) $r['days'];
        }
    }

    return [
        'ok' => true,
        'users' => $users,
        'paid' => $paid,
        'days' => $days,
        'message' => sprintf('Daily yield: %d user(s), %d day(s), $%s.', $users, $days, number_format($paid, 2)),
    ];
}

/**
 * Enroll existing pure-$50 accounts that are not yet Starter.
 *
 * @return array{ok:bool, enrolled:int, message:string}
 */
function arowin_daily_yield_backfill_enrollments(): array
{
    arowin_ensure_daily_yield_schema();
    $pdo = arowin_db();
    $rows = $pdo->query(
        "SELECT username
         FROM node_registrations
         WHERE purchased_package = '50'
           AND (power_owner IS NULL OR power_owner = '')
           AND status = 'active'
         ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $n = 0;
    foreach ($rows as $u) {
        $r = arowin_daily_yield_enroll((string) $u);
        if (!empty($r['enrolled'])) {
            $n++;
        }
    }

    return [
        'ok' => true,
        'enrolled' => $n,
        'message' => "Backfilled {$n} daily-yield enrollment(s).",
    ];
}

/**
 * Member-facing stop reason for Daily Yield History.
 */
function arowin_daily_yield_stop_reason_text(string $reason, string $username = ''): string
{
    $reason = trim($reason);
    $rankName = 'Starter';
    if ($username !== '' && function_exists('arowin_user_highest_achieved_rank')) {
        $highest = arowin_user_highest_achieved_rank($username);
        $label = trim((string) ($highest['rank_label'] ?? ''));
        if ($label !== '') {
            $rankName = $label;
        }
    }

    return match ($reason) {
        'starter_rank' => 'You achieved '
            . $rankName
            . ' rank (Rank Bonus earnings started), so Daily Yield income has been stopped.',
        'upgraded_package' => 'Daily Yield stopped because you upgraded to a higher package. Only a pure $50 package earns Daily Yield.',
        'max_days' => 'Daily Yield completed — 400 days / $100 cap reached.',
        'power_id' => 'Daily Yield stopped — power IDs are not eligible.',
        default => $reason !== ''
            ? 'Daily Yield stopped: ' . str_replace('_', ' ', $reason) . '.'
            : '',
    };
}

/**
 * Daily Yield history: credits + stop reason (rank / upgrade / completed).
 *
 * @return array{
 *   rows:list<array<string,mixed>>,
 *   total:float,
 *   status:string,
 *   stop_reason:string,
 *   stop_notice:string,
 *   stopped_at:string,
 *   subtitle:string
 * }
 */
function arowin_daily_yield_history(string $username): array
{
    arowin_ensure_daily_yield_schema();
    $username = arowin_normalize_sponsor_id($username);
    $empty = [
        'rows' => [],
        'total' => 0.0,
        'status' => '',
        'stop_reason' => '',
        'stop_notice' => '',
        'stopped_at' => '',
        'subtitle' => '',
    ];
    if ($username === '') {
        return $empty;
    }

    try {
        arowin_daily_yield_process_user($username);
    } catch (Throwable $e) {
        error_log('Daily yield history process: ' . $e->getMessage());
    }

    $pdo = arowin_db();
    $enStmt = $pdo->prepare(
        'SELECT status, stop_reason, stopped_at, days_paid, max_days, daily_amount, start_date
         FROM daily_yield_enrollments WHERE UPPER(username) = :u LIMIT 1'
    );
    $enStmt->execute([':u' => $username]);
    $enroll = $enStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $payStmt = $pdo->prepare(
        'SELECT pay_date, amount FROM daily_yield_payouts
         WHERE UPPER(username) = :u
         ORDER BY pay_date DESC, id DESC'
    );
    $payStmt->execute([':u' => $username]);
    $payouts = $payStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rows = [];
    $total = 0.0;
    foreach ($payouts as $p) {
        $amt = round((float) ($p['amount'] ?? 0), 2);
        $total = round($total + $amt, 2);
        $payDate = (string) ($p['pay_date'] ?? '');
        $when = $payDate !== '' ? date('d M Y', strtotime($payDate)) : '';
        $rows[] = [
            'id' => 0,
            'type' => 'daily_yield',
            'label' => 'Daily Yield',
            'amount' => $amt,
            'amount_fmt' => '+' . number_format($amt, 2) . ' USDT',
            'note' => '$50 package · $0.25 / day',
            'when' => $when,
            'status' => 'paid',
        ];
    }

    $status = strtolower(trim((string) ($enroll['status'] ?? '')));
    $reason = trim((string) ($enroll['stop_reason'] ?? ''));
    $stoppedAt = (string) ($enroll['stopped_at'] ?? '');
    $notice = '';
    $stopWhen = '';
    if ($status === 'stopped' || $status === 'completed') {
        $notice = arowin_daily_yield_stop_reason_text($reason !== '' ? $reason : $status, $username);
    }

    if ($notice !== '') {
        if ($stoppedAt !== '') {
            $stopWhen = function_exists('arowin_format_india_time')
                ? arowin_format_india_time($stoppedAt, 'd M Y, h:i A')
                : date('d M Y, h:i A', strtotime($stoppedAt));
        }
        array_unshift($rows, [
            'id' => 0,
            'type' => 'daily_yield',
            'label' => $status === 'completed' ? 'Daily Yield completed' : 'Daily Yield stopped',
            'amount' => 0.0,
            'amount_fmt' => '—',
            'note' => $notice,
            'when' => $stopWhen,
            'status' => 'stopped',
        ]);
    }

    $daysPaid = (int) ($enroll['days_paid'] ?? 0);
    $maxDays = (int) ($enroll['max_days'] ?? arowin_daily_yield_max_days());
    $sub = '';
    if ($status === 'active') {
        $sub = 'Active · ' . $daysPaid . '/' . $maxDays . ' days credited';
    } elseif ($status === 'stopped') {
        $sub = 'Stopped';
        if ($stopWhen !== '') {
            $sub .= ' · ' . $stopWhen;
        }
    } elseif ($status === 'completed') {
        $sub = 'Completed · ' . $daysPaid . '/' . $maxDays . ' days';
    }

    return [
        'rows' => $rows,
        'total' => $total,
        'status' => $status,
        'stop_reason' => $reason,
        'stop_notice' => $notice,
        'stopped_at' => $stoppedAt,
        'subtitle' => $sub,
    ];
}
