<?php
/**
 * Activation income engine
 *
 * - Direct referral (live join): 5% of the $50 unit ($2.50) → sponsor_id
 *   (the share-link / register sponsor — never the binary placement parent)
 * - Power IDs keep L1:R1 on that ID (binary parent = sponsor_id). Do not change
 *   Power ID create/activate/placement logic.
 * - Never 5% of the live purchased pack (that would pay $637.50 on $12,750)
 * - Matching 1:1 + rank bonus: counted and credited on that same tree ID
 */
declare(strict_types=1);

function arowin_income_unit_amount(): float
{
    return 50.0;
}

function arowin_income_direct_percent(): float
{
    return 0.05;
}

function arowin_income_matching_percent(): float
{
    return 0.10;
}

/**
 * Daily capping table (team matching bonus) by rank.
 * Base pair = 10% of $50 unit ($5). Higher ranks may raise pair_income.
 * pair_income × daily_cap = max daily income.
 *
 * @return array<string, array{pair_income:float, daily_cap:int}>
 */
function arowin_matching_daily_capping_table(): array
{
    // Base: $50 unit × 10% = $5 per 1:1 pair (L $50 + R $50)
    $base = round(arowin_income_unit_amount() * arowin_income_matching_percent(), 2);
    return [
        'starter'           => ['pair_income' => $base, 'daily_cap' => 50],
        'bronze'            => ['pair_income' => $base, 'daily_cap' => 50],
        'silver'            => ['pair_income' => $base, 'daily_cap' => 50],
        'gold'              => ['pair_income' => $base, 'daily_cap' => 50],
        'platina'           => ['pair_income' => $base, 'daily_cap' => 50],
        'diamond'           => ['pair_income' => $base, 'daily_cap' => 50],
        'blue_sapphire'     => ['pair_income' => $base, 'daily_cap' => 50],
        'ruby_elite'        => ['pair_income' => 6.0,  'daily_cap' => 60],
        'emerald_crown'     => ['pair_income' => 7.0,  'daily_cap' => 70],
        'titanium_king'     => ['pair_income' => 8.0,  'daily_cap' => 80],
        'royal_legend'      => ['pair_income' => 10.0, 'daily_cap' => 90],
        'global_ambassador' => ['pair_income' => 25.0, 'daily_cap' => 100],
    ];
}

/**
 * Matching rules for a member based on highest achieved rank.
 * Unranked members use Starter rates.
 *
 * @return array{rank_key:string, pair_income:float, daily_cap:int, max_daily_income:float}
 */
function arowin_matching_rules_for_username(string $username): array
{
    $username = arowin_normalize_sponsor_id($username);
    $rankKey = 'starter';

    if ($username !== '' && function_exists('arowin_user_highest_achieved_rank')) {
        if (function_exists('arowin_record_rank_achievements')) {
            try {
                arowin_record_rank_achievements($username);
            } catch (Throwable $e) {
                error_log('Rank record during matching rules failed for ' . $username . ': ' . $e->getMessage());
            }
        }
        $highest = arowin_user_highest_achieved_rank($username);
        $key = strtolower(trim((string) ($highest['rank_key'] ?? '')));
        if ($key !== '') {
            $rankKey = $key;
        }
    }

    $table = arowin_matching_daily_capping_table();
    $rules = $table[$rankKey] ?? $table['starter'];
    $pairIncome = round((float) $rules['pair_income'], 2);
    $dailyCap = max(0, (int) $rules['daily_cap']);

    return [
        'rank_key' => isset($table[$rankKey]) ? $rankKey : 'starter',
        'pair_income' => $pairIncome,
        'daily_cap' => $dailyCap,
        'max_daily_income' => round($pairIncome * $dailyCap, 2),
    ];
}

/**
 * Matching rules for a rank key (for UI tables).
 *
 * @return array{rank_key:string, pair_income:float, daily_cap:int, max_daily_income:float}
 */
function arowin_matching_rules_for_rank(string $rankKey): array
{
    $rankKey = strtolower(trim($rankKey));
    $table = arowin_matching_daily_capping_table();
    $key = isset($table[$rankKey]) ? $rankKey : 'starter';
    $rules = $table[$key];
    $pairIncome = round((float) $rules['pair_income'], 2);
    $dailyCap = max(0, (int) $rules['daily_cap']);

    return [
        'rank_key' => $key,
        'pair_income' => $pairIncome,
        'daily_cap' => $dailyCap,
        'max_daily_income' => round($pairIncome * $dailyCap, 2),
    ];
}

function arowin_ensure_income_schema(): void
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
        "CREATE TABLE IF NOT EXISTS `income_payouts` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `type` ENUM('referral_income','matching_income') NOT NULL,
          `amount` DECIMAL(14,2) NOT NULL,
          `source_username` VARCHAR(60) DEFAULT NULL,
          `package_code` VARCHAR(20) DEFAULT NULL,
          `pairs` INT UNSIGNED NOT NULL DEFAULT 0,
          `ref_key` VARCHAR(120) NOT NULL,
          `note` VARCHAR(255) DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_income_ref` (`ref_key`),
          KEY `idx_income_user_type` (`username`, `type`),
          KEY `idx_income_source` (`source_username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `matching_pair_state` (
          `username` VARCHAR(60) NOT NULL,
          `paid_pairs` INT UNSIGNED NOT NULL DEFAULT 0,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function arowin_income_sum(string $username, string $type): float
{
    arowin_ensure_income_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || !in_array($type, ['referral_income', 'matching_income'], true)) {
        return 0.0;
    }
    $stmt = arowin_db()->prepare(
        'SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions
         WHERE UPPER(username) = :u AND type = :t'
    );
    $stmt->execute([':u' => $username, ':t' => $type]);
    return round((float) $stmt->fetchColumn(), 2);
}

/**
 * @return list<string> binary parents from immediate parent up (excludes self)
 */
function arowin_binary_ancestor_ids(string $username, int $maxDepth = 64): array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return [];
    }

    $out = [];
    $current = $username;
    $seen = [$current => true];
    $stmt = arowin_db()->prepare(
        'SELECT binary_parent_id FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );

    for ($i = 0; $i < $maxDepth; $i++) {
        $stmt->execute([':u' => $current]);
        $parent = arowin_normalize_sponsor_id((string) ($stmt->fetchColumn() ?: ''));
        if ($parent === '' || isset($seen[$parent])) {
            break;
        }

        // Parent must exist as a real row (includes mother root ARWDOGY)
        $exists = arowin_db()->prepare(
            'SELECT 1 FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
        );
        $exists->execute([':u' => $parent]);
        if (!$exists->fetchColumn()) {
            break;
        }

        $out[] = $parent;
        $seen[$parent] = true;
        $current = $parent;
    }

    return $out;
}

/**
 * Tree ID that exists as a real row (Power ID or live ID).
 * Matching / ranks are counted on this ID. Virtual/root stubs → ''.
 */
function arowin_income_earning_username(string $username): string
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return '';
    }

    $stmt = arowin_db()->prepare(
        'SELECT username FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return '';
    }

    return arowin_normalize_sponsor_id((string) ($row['username'] ?? $username));
}

/**
 * Wallet that receives money for $username.
 * Each ID (live or Power ID) keeps its own L1:R1 / matching / rank bonus on that ID.
 */
function arowin_income_wallet_username(string $username): string
{
    return arowin_income_earning_username($username);
}

function arowin_income_can_receive(string $username): bool
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return false;
    }

    // Must be a real DB row (not virtual mother stub)
    $stmt = arowin_db()->prepare(
        'SELECT id, status, node_package, purchased_package
         FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int) ($row['id'] ?? 0) < 1) {
        return false;
    }

    // Upline must be ACTIVE with a package — free-join / pending cannot receive income
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    $nodePkg = trim((string) ($row['node_package'] ?? ''));
    $purchased = trim((string) ($row['purchased_package'] ?? ''));
    $hasPackage = (function_exists('arowin_normalize_package')
        && (arowin_normalize_package($nodePkg) !== '' || arowin_normalize_package($purchased) !== ''))
        || ($nodePkg !== '' && ctype_digit($nodePkg))
        || ($purchased !== '' && ctype_digit($purchased));
    if ($status !== 'active' || !$hasPackage) {
        return false;
    }

    return true;
}

/**
 * @return array{ok:bool, created:bool, message:string}
 */
function arowin_income_pay(
    string $username,
    string $type,
    float $amount,
    string $refKey,
    ?string $sourceUsername = null,
    string $packageCode = '',
    int $pairs = 0,
    string $note = ''
): array {
    arowin_ensure_income_schema();
    $username = arowin_normalize_sponsor_id($username);
    $amount = round($amount, 2);
    $refKey = trim($refKey);
    if ($username === '' || $amount <= 0 || $refKey === '') {
        return ['ok' => false, 'created' => false, 'message' => 'Invalid income payout.'];
    }
    if (!in_array($type, ['referral_income', 'matching_income'], true)) {
        return ['ok' => false, 'created' => false, 'message' => 'Invalid income type.'];
    }
    $creditUser = arowin_income_wallet_username($username);
    if ($creditUser === '') {
        return ['ok' => false, 'created' => false, 'message' => 'Recipient cannot receive income.'];
    }
    if (!arowin_income_can_receive($creditUser)) {
        return ['ok' => false, 'created' => false, 'message' => 'Recipient cannot receive income.'];
    }

    $pdo = arowin_db();
    $refKey = mb_substr($refKey, 0, 120);
    $ownTx = !$pdo->inTransaction();
    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        $chk = $pdo->prepare('SELECT id FROM income_payouts WHERE ref_key = :k LIMIT 1');
        $chk->execute([':k' => $refKey]);
        if ($chk->fetch()) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => true, 'created' => false, 'message' => 'Already paid.'];
        }

        $ins = $pdo->prepare(
            'INSERT INTO income_payouts
                (username, type, amount, source_username, package_code, pairs, ref_key, note)
             VALUES
                (:u, :t, :a, :s, :p, :pairs, :k, :n)'
        );
        $ins->execute([
            ':u' => $username,
            ':t' => $type,
            ':a' => $amount,
            ':s' => $sourceUsername ? arowin_normalize_sponsor_id($sourceUsername) : null,
            ':p' => $packageCode !== '' ? mb_substr($packageCode, 0, 20) : null,
            ':pairs' => max(0, $pairs),
            ':k' => $refKey,
            ':n' => $note !== '' ? mb_substr($note, 0, 255) : null,
        ]);

        $creditNote = $note !== '' ? $note : ($type === 'referral_income' ? 'Direct referral 5%' : 'Matching 1:1');
        $credit = arowin_wallet_credit(
            $creditUser,
            $amount,
            $type,
            $sourceUsername,
            $creditNote
        );
        if (empty($credit['ok'])) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return [
                'ok' => false,
                'created' => false,
                'message' => (string) ($credit['message'] ?? 'Wallet credit failed.'),
            ];
        }

        if ($ownTx && $pdo->inTransaction()) {
            $pdo->commit();
        }
        return ['ok' => true, 'created' => true, 'message' => 'Income paid.'];
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
            return ['ok' => true, 'created' => false, 'message' => 'Already paid.'];
        }
        error_log('Income pay error: ' . $e->getMessage());
        return ['ok' => false, 'created' => false, 'message' => 'Income payout failed.'];
    }
}

function arowin_matching_paid_pairs(string $username): int
{
    arowin_ensure_income_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return 0;
    }
    $stmt = arowin_db()->prepare(
        'SELECT paid_pairs FROM matching_pair_state WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function arowin_matching_set_paid_pairs(string $username, int $pairs): void
{
    arowin_ensure_income_schema();
    $username = arowin_normalize_sponsor_id($username);
    $pairs = max(0, $pairs);
    if ($username === '') {
        return;
    }
    arowin_db()->prepare(
        'INSERT INTO matching_pair_state (username, paid_pairs)
         VALUES (:u, :p)
         ON DUPLICATE KEY UPDATE paid_pairs = VALUES(paid_pairs), updated_at = CURRENT_TIMESTAMP'
    )->execute([':u' => $username, ':p' => $pairs]);
}

/**
 * Flush new 1:1 pairs for one member using rank-based pair income + daily capping.
 * Pairs count ACTIVE packaged members only (free join / pending do NOT count).
 *
 * @return array{ok:bool, pairs:int, amount:float, message:string}
 */
function arowin_flush_matching_income(string $username, string $sourceUsername, string $packageCode): array
{
    if (!function_exists('arowin_count_binary_starters')) {
        require_once __DIR__ . '/team-repository.php';
    }
    if (!function_exists('arowin_user_highest_achieved_rank')) {
        require_once __DIR__ . '/ranks-repository.php';
    }

    $treeId = arowin_income_earning_username($username);
    $sourceUsername = arowin_normalize_sponsor_id($sourceUsername);
    if ($treeId === '') {
        return ['ok' => true, 'pairs' => 0, 'amount' => 0.0, 'message' => 'Skipped empty recipient.'];
    }
    $payTo = arowin_income_wallet_username($treeId);
    if ($payTo === '' || !arowin_income_can_receive($payTo)) {
        return ['ok' => true, 'pairs' => 0, 'amount' => 0.0, 'message' => 'Skipped — upline not active / no package.'];
    }

    // Matching volume = active package holders only (not free registrations)
    $left = arowin_count_binary_starters($treeId, 'left');
    $right = arowin_count_binary_starters($treeId, 'right');
    $pairsNow = min($left, $right);
    $paid = arowin_matching_paid_pairs($treeId);
    $newPairs = $pairsNow - $paid;
    if ($newPairs <= 0) {
        return ['ok' => true, 'pairs' => 0, 'amount' => 0.0, 'message' => 'No new pairs.'];
    }

    $rules = arowin_matching_rules_for_username($treeId);
    $dailyCap = (int) $rules['daily_cap'];
    // 1:1 pair = Left $50 + Right $50 → earn 10% of matched $50 unit
    $perPair = round(arowin_income_unit_amount() * arowin_income_matching_percent(), 2);
    $rankPair = round((float) $rules['pair_income'], 2);
    // Higher ranks may unlock a higher pair rate; never below the 10% base
    if ($rankPair > $perPair) {
        $perPair = $rankPair;
    }
    $paidToday = arowin_matching_pairs_paid_today($treeId);
    $room = max(0, $dailyCap - $paidToday);
    if ($room <= 0) {
        return [
            'ok' => true,
            'pairs' => 0,
            'amount' => 0.0,
            'message' => 'Daily matching cap reached (' . $dailyCap . ' pairs / $' . number_format((float) $rules['max_daily_income'], 2) . ').',
        ];
    }
    if ($newPairs > $room) {
        $newPairs = $room;
        $pairsNow = $paid + $newPairs;
    }

    $amount = round($newPairs * $perPair, 2);
    // Stable key by tree ID + pair watermark (not source), so catch-up always works
    $ref = 'match:' . $treeId . ':upto:' . $pairsNow;

    $matchNote = 'Matching 1:1 × ' . $newPairs
        . ' (active L/R packages, 10% of $' . number_format(arowin_income_unit_amount(), 0)
        . ' unit = $' . number_format($perPair, 2) . '/pair, '
        . str_replace('_', ' ', (string) $rules['rank_key']) . ')';

    $pay = arowin_income_pay(
        $treeId,
        'matching_income',
        $amount,
        $ref,
        $sourceUsername !== '' ? $sourceUsername : null,
        $packageCode,
        $newPairs,
        $matchNote
    );

    if (!empty($pay['created'])) {
        arowin_matching_set_paid_pairs($treeId, $pairsNow);
        return [
            'ok' => true,
            'pairs' => $newPairs,
            'amount' => $amount,
            'message' => 'Paid.',
        ];
    }

    if (!empty($pay['ok'])) {
        // Already recorded for this watermark — keep state in sync
        arowin_matching_set_paid_pairs($treeId, max($paid, $pairsNow));
        return [
            'ok' => true,
            'pairs' => 0,
            'amount' => 0.0,
            'message' => (string) ($pay['message'] ?? 'Already paid.'),
        ];
    }

    error_log(
        'Matching income failed for ' . $treeId
        . ' (pay ' . $payTo . ', source ' . $sourceUsername . ', pairs ' . $newPairs . '): '
        . (string) ($pay['message'] ?? 'unknown')
    );

    return [
        'ok' => false,
        'pairs' => 0,
        'amount' => 0.0,
        'message' => (string) ($pay['message'] ?? 'Matching payout failed.'),
    ];
}

/**
 * Pairs already paid today (India calendar day).
 */
function arowin_matching_pairs_paid_today(string $username): int
{
    arowin_ensure_income_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return 0;
    }

    try {
        $tz = new DateTimeZone('Asia/Kolkata');
        $startIst = new DateTimeImmutable('today', $tz);
        $endIst = $startIst->modify('+1 day');
        $startUtc = $startIst->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $endUtc = $endIst->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        $startUtc = gmdate('Y-m-d 00:00:00');
        $endUtc = gmdate('Y-m-d 00:00:00', time() + 86400);
    }

    $stmt = arowin_db()->prepare(
        "SELECT COALESCE(SUM(pairs), 0) FROM income_payouts
         WHERE UPPER(username) = :u
           AND type = 'matching_income'
           AND created_at >= :a
           AND created_at < :b"
    );
    $stmt->execute([':u' => $username, ':a' => $startUtc, ':b' => $endUtc]);
    return (int) $stmt->fetchColumn();
}

/**
 * True if this child already paid direct referral (any recipient / package code).
 */
function arowin_referral_already_paid(string $buyerUsername, string $packageCode, string $payTo = ''): bool
{
    arowin_ensure_income_schema();
    $buyerUsername = arowin_normalize_sponsor_id($buyerUsername);
    $packageCode = arowin_normalize_package($packageCode);
    $payTo = arowin_normalize_sponsor_id($payTo);
    if ($buyerUsername === '') {
        return false;
    }
    if ($payTo === '' && $packageCode === '') {
        return false;
    }
    try {
        // One direct-referral credit per joiner (sponsor or placement — never both)
        $any = arowin_db()->prepare(
            "SELECT 1 FROM income_payouts
             WHERE type = 'referral_income'
               AND UPPER(source_username) = :b
             LIMIT 1"
        );
        $any->execute([':b' => $buyerUsername]);
        if ($any->fetchColumn()) {
            return true;
        }
        if ($payTo !== '') {
            $stmt = arowin_db()->prepare(
                "SELECT 1 FROM income_payouts
                 WHERE type = 'referral_income'
                   AND UPPER(source_username) = :b
                   AND UPPER(username) = :u
                 LIMIT 1"
            );
            $stmt->execute([':b' => $buyerUsername, ':u' => $payTo]);
        } else {
            $stmt = arowin_db()->prepare(
                "SELECT 1 FROM income_payouts
                 WHERE type = 'referral_income'
                   AND UPPER(source_username) = :b
                   AND package_code = :p
                 LIMIT 1"
            );
            $stmt->execute([':b' => $buyerUsername, ':p' => $packageCode]);
        }
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (Throwable $e) {
        // fall through
    }
    return false;
}

/**
 * Pay direct referral: 5% of the $50 unit ($2.50).
 * Live join → sponsor_id. Power ID → binary parent (unchanged).
 *
 * @return array{paid:float, skip:string, pay_to:string}
 */
function arowin_try_pay_direct_referral(string $buyerUsername, string $packageCode, string $payToOverride = ''): array
{
    $buyerUsername = arowin_normalize_sponsor_id($buyerUsername);
    $packageCode = '50';
    $info = arowin_package_info($packageCode);
    if ($buyerUsername === '' || !$info) {
        return ['paid' => 0.0, 'skip' => 'Invalid buyer/package.', 'pay_to' => ''];
    }

    $buyer = arowin_get_user_by_username($buyerUsername);
    if (!$buyer) {
        return ['paid' => 0.0, 'skip' => 'Buyer not found.', 'pay_to' => ''];
    }
    $status = strtolower(trim((string) ($buyer['status'] ?? '')));
    if ($status !== 'active') {
        return ['paid' => 0.0, 'skip' => 'Buyer not active.', 'pay_to' => ''];
    }

    $isPower = trim((string) ($buyer['power_owner'] ?? '')) !== '';
    $override = arowin_normalize_sponsor_id($payToOverride);
    $sponsorId = arowin_normalize_sponsor_id((string) ($buyer['sponsor_id'] ?? ''));

    if ($isPower) {
        // Power IDs: L1:R1 stays on the tree parent (sponsor_id = binary_parent_id).
        $sponsor = $override !== '' ? $override : arowin_normalize_sponsor_id((string) ($buyer['binary_parent_id'] ?? ''));
        if ($sponsor === '') {
            $sponsor = $sponsorId;
        }
    } else {
        // Live join: only the register/share-link sponsor. Placement parent (Y) is never paid.
        $sponsor = $sponsorId;
        if ($override !== '' && $override !== $sponsor) {
            return [
                'paid' => 0.0,
                'skip' => 'Placement parent is not the sponsor.',
                'pay_to' => $override,
            ];
        }
    }
    if ($sponsor === '') {
        return ['paid' => 0.0, 'skip' => 'No sponsor on buyer.', 'pay_to' => ''];
    }

    $payTo = arowin_income_earning_username($sponsor);
    if ($payTo === '') {
        return ['paid' => 0.0, 'skip' => 'Sponsor account missing: ' . $sponsor, 'pay_to' => ''];
    }
    if (!arowin_income_can_receive($payTo)) {
        return ['paid' => 0.0, 'skip' => 'Sponsor cannot receive income: ' . $payTo, 'pay_to' => $payTo];
    }
    if (arowin_referral_already_paid($buyerUsername, $packageCode, $payTo)) {
        return ['paid' => 0.0, 'skip' => 'Already paid.', 'pay_to' => $payTo];
    }

    $pkgAmount = round((float) $info['amount'], 2);
    $refAmt = round($pkgAmount * arowin_income_direct_percent(), 2);
    if ($refAmt <= 0) {
        return ['paid' => 0.0, 'skip' => 'Referral amount is zero.', 'pay_to' => $payTo];
    }

    $refKey = 'referral:' . $payTo . ':' . $buyerUsername . ':' . $packageCode;
    $pay = arowin_income_pay(
        $payTo,
        'referral_income',
        $refAmt,
        $refKey,
        $buyerUsername,
        $packageCode,
        0,
        'Direct referral 5% of $' . number_format($pkgAmount, 2) . ' · sponsor (' . $buyerUsername . ')'
    );
    if (!empty($pay['created'])) {
        return ['paid' => $refAmt, 'skip' => '', 'pay_to' => $payTo];
    }
    if (!empty($pay['ok'])) {
        return ['paid' => 0.0, 'skip' => (string) ($pay['message'] ?? 'Already paid.'), 'pay_to' => $payTo];
    }

    $skip = (string) ($pay['message'] ?? 'Referral payout failed.');
    error_log('Direct referral failed for ' . $buyerUsername . ' → ' . $payTo . ': ' . $skip);
    return ['paid' => 0.0, 'skip' => $skip, 'pay_to' => $payTo];
}

/**
 * Direct referral for live members who registered with this sponsor_id.
 */
function arowin_pay_sponsored_direct_referrals(string $sponsorUsername): float
{
    $sponsorUsername = arowin_normalize_sponsor_id($sponsorUsername);
    if ($sponsorUsername === '' || !arowin_income_can_receive($sponsorUsername)) {
        return 0.0;
    }

    try {
        $stmt = arowin_db()->prepare(
            "SELECT username, status, power_owner, purchased_package, node_package
             FROM node_registrations
             WHERE UPPER(sponsor_id) = :s
               AND (power_owner IS NULL OR power_owner = '')
             ORDER BY id ASC"
        );
        $stmt->execute([':s' => $sponsorUsername]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Sponsor direct referral scan failed for ' . $sponsorUsername . ': ' . $e->getMessage());
        return 0.0;
    }

    $paid = 0.0;
    foreach ($rows as $row) {
        $child = arowin_normalize_sponsor_id((string) ($row['username'] ?? ''));
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($child === '' || $status !== 'active') {
            continue;
        }
        $hasUnit = function_exists('arowin_normalize_package')
            && (arowin_normalize_package((string) ($row['node_package'] ?? '')) !== ''
                || arowin_normalize_package((string) ($row['purchased_package'] ?? '')) !== '');
        if (!$hasUnit) {
            continue;
        }
        $try = arowin_try_pay_direct_referral($child, '50', $sponsorUsername);
        $paid = round($paid + (float) $try['paid'], 2);
    }

    return $paid;
}

/**
 * Power ID L1:R1: immediate Left child + immediate Right child (max 2).
 * Live spillover under this ID is skipped (income goes to the child's sponsor).
 */
function arowin_pay_lr_direct_referrals(string $username): float
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || !arowin_income_can_receive($username)) {
        return 0.0;
    }
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $paid = 0.0;
    foreach (['left', 'right'] as $leg) {
        $child = getBinaryTreeChild($username, $leg);
        if ($child === null || $child === '') {
            continue;
        }
        $child = arowin_normalize_sponsor_id((string) $child);
        $row = arowin_get_user_by_username($child);
        if (!$row) {
            continue;
        }
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status !== 'active') {
            continue;
        }
        $isPower = trim((string) ($row['power_owner'] ?? '')) !== '';
        if (!$isPower) {
            // Live member under this slot: sponsor_id gets direct referral, not this placement ID.
            continue;
        }
        $try = arowin_try_pay_direct_referral($child, '50', $username);
        $paid = round($paid + (float) $try['paid'], 2);
    }
    return $paid;
}

/**
 * After activation: sponsor directs for this live ID, plus Power ID L1:R1 (unchanged).
 */
function arowin_income_backfill_direct_referrals(string $sponsorUsername): float
{
    $sponsorUsername = arowin_normalize_sponsor_id($sponsorUsername);
    if ($sponsorUsername === '') {
        return 0.0;
    }

    $paid = arowin_pay_sponsored_direct_referrals($sponsorUsername);
    // Live ID L1:R1 — Power IDs sitting on this member's immediate left/right.
    $paid = round($paid + arowin_pay_lr_direct_referrals($sponsorUsername), 2);
    foreach (arowin_list_owned_power_usernames($sponsorUsername) as $pid) {
        $paid = round($paid + arowin_pay_lr_direct_referrals($pid), 2);
    }
    return $paid;
}

/**
 * Power IDs owned by this human member (empty if $username is itself a Power ID).
 *
 * @return list<string>
 */
function arowin_list_owned_power_usernames(string $owner): array
{
    $owner = arowin_normalize_sponsor_id($owner);
    if ($owner === '') {
        return [];
    }
    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }
    try {
        $stmt = arowin_db()->prepare(
            'SELECT username FROM node_registrations
             WHERE UPPER(power_owner) = :o
             ORDER BY id ASC'
        );
        $stmt->execute([':o' => $owner]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $uid) {
            $uid = arowin_normalize_sponsor_id((string) $uid);
            if ($uid !== '') {
                $out[] = $uid;
            }
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * This ID plus its owned Power IDs (for matching + rank catch-up).
 *
 * @return list<string>
 */
function arowin_income_tree_usernames(string $username): array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return [];
    }
    $ids = [$username];
    foreach (arowin_list_owned_power_usernames($username) as $pid) {
        if (!in_array($pid, $ids, true)) {
            $ids[] = $pid;
        }
    }
    return $ids;
}

/**
 * Pay new 1:1 matching + record ranks for an ID (and its Power IDs when it is the owner).
 *
 * @return array{matching:float, matching_pairs:int}
 */
function arowin_flush_tree_matching_and_ranks(
    string $username,
    string $sourceUsername,
    string $packageCode,
    bool $includeAncestors = true
): array
{
    if (!function_exists('arowin_record_rank_achievements') && is_file(__DIR__ . '/admin-metrics-repository.php')) {
        require_once __DIR__ . '/admin-metrics-repository.php';
    }
    if (!function_exists('arowin_rank_ladder') && is_file(__DIR__ . '/ranks-repository.php')) {
        require_once __DIR__ . '/ranks-repository.php';
    }
    if (!function_exists('arowin_rank_bonus_process_user') && is_file(__DIR__ . '/rank-bonus-repository.php')) {
        require_once __DIR__ . '/rank-bonus-repository.php';
    }

    $username = arowin_normalize_sponsor_id($username);
    $sourceUsername = arowin_normalize_sponsor_id($sourceUsername);
    $packageCode = arowin_normalize_package($packageCode);
    if ($packageCode === '') {
        $packageCode = '50';
    }

    static $done = [];
    $guardKey = $username . '|' . $sourceUsername . '|' . $packageCode . '|' . ($includeAncestors ? '1' : '0');
    if ($username !== '' && isset($done[$guardKey])) {
        return ['matching' => 0.0, 'matching_pairs' => 0];
    }
    if ($username !== '') {
        $done[$guardKey] = true;
    }

    $rawTargets = arowin_income_tree_usernames($username);
    if ($includeAncestors) {
        $rawTargets = array_merge($rawTargets, arowin_binary_ancestor_ids($username));
    }
    $targets = [];
    $seen = [];
    foreach ($rawTargets as $uid) {
        $uid = arowin_normalize_sponsor_id((string) $uid);
        if ($uid === '' || isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;
        $targets[] = $uid;
    }

    $matchingPaid = 0.0;
    $matchingPairs = 0;
    foreach ($targets as $uid) {
        $flush = arowin_flush_matching_income($uid, $sourceUsername !== '' ? $sourceUsername : $uid, $packageCode);
        if (!empty($flush['ok'])) {
            $matchingPaid = round($matchingPaid + (float) ($flush['amount'] ?? 0), 2);
            $matchingPairs += (int) ($flush['pairs'] ?? 0);
        }
        if (function_exists('arowin_record_rank_achievements')) {
            try {
                arowin_record_rank_achievements($uid);
            } catch (Throwable $e) {
                error_log('Rank record during income flush failed for ' . $uid . ': ' . $e->getMessage());
            }
        }
        if (function_exists('arowin_rank_bonus_process_user')) {
            try {
                arowin_rank_bonus_process_user($uid);
            } catch (Throwable $e) {
                // non-fatal
            }
        }
    }

    return ['matching' => $matchingPaid, 'matching_pairs' => $matchingPairs];
}

/**
 * Run after successful package activation.
 *
 * @return array{
 *   ok:bool,
 *   referral:float,
 *   matching:float,
 *   matching_pairs:int,
 *   message:string
 * }
 */
function arowin_process_activation_income(string $buyerUsername, string $packageCode): array
{
    arowin_ensure_income_schema();
    if (!function_exists('getUserByUserId')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $buyerUsername = arowin_normalize_sponsor_id($buyerUsername);
    $packageCode = arowin_normalize_package($packageCode);
    $info = arowin_package_info($packageCode);
    if ($buyerUsername === '' || !$info) {
        return [
            'ok' => false,
            'referral' => 0.0,
            'matching' => 0.0,
            'matching_pairs' => 0,
            'message' => 'Invalid activation income context.',
        ];
    }

    $buyer = arowin_get_user_by_username($buyerUsername);
    if (!$buyer || !empty($buyer['power_owner'])) {
        return [
            'ok' => true,
            'referral' => 0.0,
            'matching' => 0.0,
            'matching_pairs' => 0,
            'message' => 'No income for power IDs.',
        ];
    }

    $referralPaid = 0.0;
    $matchingPaid = 0.0;
    $matchingPairs = 0;

    // 1) Direct referral $2.50 → register/share-link sponsor (not the binary placement parent)
    $sponsor = arowin_normalize_sponsor_id((string) ($buyer['sponsor_id'] ?? ''));
    $refTry = arowin_try_pay_direct_referral($buyerUsername, '50', $sponsor);
    $referralPaid = (float) $refTry['paid'];
    $referralSkip = (string) $refTry['skip'];
    if ($referralPaid <= 0 && $referralSkip !== '' && $sponsor !== '') {
        error_log('Direct referral skipped for ' . $buyerUsername . ' (sponsor ' . $sponsor . '): ' . $referralSkip);
    }

    // 1b) Catch-up: this ID's sponsored live joins + Power ID L1:R1 (power logic unchanged)
    $referralPaid = round($referralPaid + arowin_income_backfill_direct_referrals($buyerUsername), 2);

    // 2) Matching 1:1 + ranks — isolated so a rank error cannot drop referral already paid
    if (!function_exists('arowin_record_rank_achievements') && is_file(__DIR__ . '/admin-metrics-repository.php')) {
        require_once __DIR__ . '/admin-metrics-repository.php';
    }
    if (!function_exists('arowin_rank_bonus_process_user') && is_file(__DIR__ . '/rank-bonus-repository.php')) {
        require_once __DIR__ . '/rank-bonus-repository.php';
    }
    try {
        $tree = arowin_flush_tree_matching_and_ranks($buyerUsername, $buyerUsername, $packageCode);
        $matchingPaid = (float) ($tree['matching'] ?? 0);
        $matchingPairs = (int) ($tree['matching_pairs'] ?? 0);
    } catch (Throwable $e) {
        error_log('Matching/rank flush failed after referral for ' . $buyerUsername . ': ' . $e->getMessage());
    }

    $msg = sprintf(
        'Income: referral $%s, matching $%s (%d pairs).',
        number_format($referralPaid, 2),
        number_format($matchingPaid, 2),
        $matchingPairs
    );
    if ($referralPaid <= 0 && $referralSkip !== '') {
        $msg .= ' Referral note: ' . $referralSkip;
    }

    return [
        'ok' => true,
        'referral' => $referralPaid,
        'matching' => $matchingPaid,
        'matching_pairs' => $matchingPairs,
        'message' => $msg,
    ];
}

/**
 * Immediate Left + Right usernames under this ID.
 *
 * @return array<string, true>
 */
function arowin_income_immediate_lr_set(string $username): array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return [];
    }
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }
    $set = [];
    foreach (['left', 'right'] as $leg) {
        $child = getBinaryTreeChild($username, $leg);
        $uid = arowin_normalize_sponsor_id((string) ($child ?? ''));
        if ($uid !== '') {
            $set[$uid] = true;
        }
    }
    return $set;
}

/**
 * One-time: undo mother rollup ($635 of 254 × $2.50) and put L1:R1 / matching / rank bonus on each ID.
 * Wallet history is never deleted — extra amounts are reversed with negative income rows.
 */
function arowin_income_rebalance_owner_rollup(string $owner): void
{
    static $done = [];
    $owner = arowin_normalize_sponsor_id($owner);
    if ($owner === '' || isset($done[$owner])) {
        return;
    }

    $self = function_exists('arowin_get_user_by_username') ? arowin_get_user_by_username($owner) : null;
    if (!$self || !empty($self['power_owner'])) {
        $done[$owner] = true;
        return;
    }

    $dir = dirname(__DIR__) . '/storage/cache';
    $safe = preg_replace('/[^a-z0-9_\-]/i', '_', 'rebal_lr_v2_' . strtolower($owner)) ?: 'rebal';
    $flag = $dir . '/' . $safe . '.done';
    if (is_file($flag)) {
        $done[$owner] = true;
        return;
    }

    if (!function_exists('arowin_wallet_income_signed') && is_file(__DIR__ . '/wallet-repository.php')) {
        require_once __DIR__ . '/wallet-repository.php';
    }
    if (!function_exists('arowin_wallet_income_signed')) {
        return;
    }

    $pdo = arowin_db();
    $powerIds = arowin_list_owned_power_usernames($owner);
    $keepChildren = arowin_income_immediate_lr_set($owner);

    try {
        $payouts = $pdo->prepare(
            "SELECT source_username, package_code, amount
             FROM income_payouts
             WHERE type = 'referral_income' AND UPPER(username) = :o"
        );
        $payouts->execute([':o' => $owner]);
        foreach ($payouts->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $src = arowin_normalize_sponsor_id((string) ($row['source_username'] ?? ''));
            $amt = round((float) ($row['amount'] ?? 0), 2);
            if ($src === '' || $amt <= 0 || isset($keepChildren[$src])) {
                continue;
            }
            arowin_wallet_income_signed(
                $owner,
                -$amt,
                'referral_income',
                $src,
                'Rebalance L1:R1 reverse extra referral from ' . $src
            );
            $child = arowin_get_user_by_username($src);
            $parent = arowin_normalize_sponsor_id((string) ($child['binary_parent_id'] ?? $child['sponsor_id'] ?? ''));
            if ($parent === '' || $parent === $owner) {
                continue;
            }
            $pkg = !empty($child['power_owner'])
                ? '50'
                : (function_exists('arowin_normalize_package')
                    ? arowin_normalize_package((string) ($row['package_code'] ?? '50'))
                    : '50');
            if ($pkg === '') {
                $pkg = '50';
            }
            arowin_try_pay_direct_referral($src, $pkg, $parent);
        }
    } catch (Throwable $e) {
        error_log('Rebalance referral failed for ' . $owner . ': ' . $e->getMessage());
    }

    try {
        $collectStmt = $pdo->prepare(
            "SELECT id, type, amount, counterparty, note
             FROM wallet_transactions
             WHERE UPPER(username) = :o
               AND type IN ('referral_income','matching_income','rank_bonus')
               AND note LIKE 'Collected from Power ID %'"
        );
        $collectStmt->execute([':o' => $owner]);
        foreach ($collectStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $tx) {
            $pid = arowin_normalize_sponsor_id((string) ($tx['counterparty'] ?? ''));
            $amt = round((float) ($tx['amount'] ?? 0), 2);
            $type = (string) ($tx['type'] ?? '');
            if ($pid === '' || $amt <= 0 || $type === '') {
                continue;
            }
            arowin_wallet_income_signed(
                $owner,
                -$amt,
                $type,
                $pid,
                'Rebalance return collect ' . $type . ' to ' . $pid
            );
            arowin_wallet_income_signed(
                $pid,
                $amt,
                $type,
                $owner,
                'Rebalance restore ' . $type . ' from owner ' . $owner
            );
        }
    } catch (Throwable $e) {
        error_log('Rebalance collect reverse failed for ' . $owner . ': ' . $e->getMessage());
    }

    $moveLedgerToId = static function (string $pid, string $type, float $owed) use ($owner): void {
        $owed = round($owed, 2);
        if ($pid === '' || $owed <= 0) {
            return;
        }
        $have = $type === 'rank_bonus'
            ? (function_exists('arowin_rank_bonus_sum') ? arowin_rank_bonus_sum($pid) : 0.0)
            : arowin_income_sum($pid, $type);
        $gap = round($owed - $have, 2);
        if ($gap <= 0) {
            return;
        }
        arowin_wallet_income_signed(
            $pid,
            $gap,
            $type,
            $owner,
            'Rebalance ' . $type . ' onto ' . $pid
        );
        arowin_wallet_income_signed(
            $owner,
            -$gap,
            $type,
            $pid,
            'Rebalance ' . $type . ' off owner onto ' . $pid
        );
    };

    if ($powerIds !== []) {
        $place = implode(',', array_fill(0, count($powerIds), '?'));
        try {
            $matchQ = $pdo->prepare(
                "SELECT UPPER(username) AS u, COALESCE(SUM(amount), 0) AS total
                 FROM income_payouts
                 WHERE type = 'matching_income' AND UPPER(username) IN ($place)
                 GROUP BY UPPER(username)"
            );
            $matchQ->execute($powerIds);
            foreach ($matchQ->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $moveLedgerToId(
                    arowin_normalize_sponsor_id((string) ($row['u'] ?? '')),
                    'matching_income',
                    (float) ($row['total'] ?? 0)
                );
            }
        } catch (Throwable $e) {
            error_log('Rebalance matching failed for ' . $owner . ': ' . $e->getMessage());
        }
        try {
            $rbQ = $pdo->prepare(
                "SELECT UPPER(username) AS u, COALESCE(SUM(amount), 0) AS total
                 FROM rank_bonus_payouts
                 WHERE UPPER(username) IN ($place)
                 GROUP BY UPPER(username)"
            );
            $rbQ->execute($powerIds);
            foreach ($rbQ->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $moveLedgerToId(
                    arowin_normalize_sponsor_id((string) ($row['u'] ?? '')),
                    'rank_bonus',
                    (float) ($row['total'] ?? 0)
                );
            }
        } catch (Throwable $e) {
            error_log('Rebalance rank bonus failed for ' . $owner . ': ' . $e->getMessage());
        }
    }

    try {
        arowin_income_backfill_direct_referrals($owner);
    } catch (Throwable $e) {
        error_log('Rebalance L1:R1 pay failed for ' . $owner . ': ' . $e->getMessage());
    }

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($flag, (string) time(), LOCK_EX);
    $done[$owner] = true;
}

/**
 * Kept for call-site compatibility. Power ID income stays on that ID (not collected to mother).
 */
function arowin_income_collect_from_power_ids(string $owner): float
{
    unset($owner);
    return 0.0;
}

/**
 * Fast dashboard catch-up: this ID only (2 directs + own matching/ranks). No 254-ID walk, no 12s throttle.
 */
function arowin_income_page_catchup(string $username): void
{
    static $done = [];
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || isset($done[$username])) {
        return;
    }
    $done[$username] = true;

    try {
        arowin_income_rebalance_owner_rollup($username);
        arowin_income_backfill_direct_referrals($username);
    } catch (Throwable $e) {
        error_log('Income referral catch-up failed for ' . $username . ': ' . $e->getMessage());
    }

    // Unlock due rank-bonus weeks first. Matching / rank BFS on large teams can
    // time out; those failures must not skip wallet credit.
    try {
        if (function_exists('arowin_rank_bonus_process_user')) {
            arowin_rank_bonus_process_user($username);
        }
    } catch (Throwable $e) {
        error_log('Income rank-bonus catch-up failed for ' . $username . ': ' . $e->getMessage());
    }

    try {
        $pkg = '50';
        $user = function_exists('arowin_get_user_by_username') ? arowin_get_user_by_username($username) : null;
        if ($user && function_exists('arowin_normalize_package')) {
            $p = arowin_normalize_package((string) ($user['purchased_package'] ?? $user['node_package'] ?? ''));
            if ($p !== '') {
                $pkg = $p;
            }
        }
        arowin_flush_matching_income($username, $username, $pkg);
    } catch (Throwable $e) {
        error_log('Income matching catch-up failed for ' . $username . ': ' . $e->getMessage());
    }

    try {
        if (function_exists('arowin_record_rank_achievements')) {
            arowin_record_rank_achievements($username);
        }
        if (function_exists('arowin_rank_bonus_process_user')) {
            arowin_rank_bonus_process_user($username);
        }
        if (function_exists('arowin_daily_yield_process_user')) {
            arowin_daily_yield_process_user($username);
        }
    } catch (Throwable $e) {
        error_log('Income page catch-up failed for ' . $username . ': ' . $e->getMessage());
    }
}
