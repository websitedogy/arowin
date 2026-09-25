<?php
/**
 * Team Collaborations — helpers (scoped to this feature folder)
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/team-repository.php';
require_once dirname(__DIR__, 3) . '/includes/genealogy-helper.php';

/**
 * @return 'all'|'left'|'right'
 */
function arowin_collab_normalize_tab(?string $tab): string
{
    $tab = strtolower(trim((string) $tab));
    if ($tab === 'left' || $tab === 'right') {
        return $tab;
    }
    return 'all';
}

/**
 * Usernames under root's left or right binary child (BFS).
 *
 * @return array<string,true>
 */
function arowin_collab_subtree_usernames(string $rootUsername, string $leg): array
{
    if (!function_exists('getBinaryTreeChild')) {
        require_once dirname(__DIR__, 3) . '/includes/genealogy-helper.php';
    }

    $rootUsername = arowin_normalize_sponsor_id($rootUsername);
    $leg = $leg === 'right' ? 'right' : 'left';
    if ($rootUsername === '') {
        return [];
    }

    $start = getBinaryTreeChild($rootUsername, $leg);
    if ($start === null || $start === '') {
        return [];
    }

    $out = [];
    $queue = [$start];
    $guard = 0;
    while ($queue !== [] && $guard < 4000) {
        $guard++;
        $uid = arowin_normalize_sponsor_id((string) array_shift($queue));
        if ($uid === '' || isset($out[$uid])) {
            continue;
        }
        $out[$uid] = true;
        $left = getBinaryTreeChild($uid, 'left');
        $right = getBinaryTreeChild($uid, 'right');
        if ($left !== null && $left !== '') {
            $queue[] = $left;
        }
        if ($right !== null && $right !== '') {
            $queue[] = $right;
        }
    }

    return $out;
}

/**
 * Auto Power IDs created by this member's $150+ package (not $50, not live referrals).
 * Each row includes `leg` = left|right.
 *
 * @return list<array<string,mixed>>
 */
function arowin_collab_list_members(string $rootUsername, string $tab): array
{
    $rootUsername = arowin_normalize_sponsor_id($rootUsername);
    $tab = arowin_collab_normalize_tab($tab);
    if ($rootUsername === '') {
        return [];
    }

    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }

    $stmt = arowin_db()->prepare(
        'SELECT username, full_name, email, phone, sponsor_id, binary_parent_id,
                binary_placement, node_package, purchased_package, power_owner,
                status, created_at, wallet_balance
         FROM node_registrations
         WHERE UPPER(power_owner) = :u
         ORDER BY id ASC'
    );
    $stmt->execute([':u' => $rootUsername]);
    $owned = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($owned === []) {
        return [];
    }

    $leftSet = arowin_collab_subtree_usernames($rootUsername, 'left');
    $rightSet = arowin_collab_subtree_usernames($rootUsername, 'right');

    $out = [];
    foreach ($owned as $row) {
        $uid = arowin_normalize_sponsor_id((string) ($row['username'] ?? ''));
        if ($uid === '') {
            continue;
        }
        if (isset($leftSet[$uid])) {
            $leg = 'left';
        } elseif (isset($rightSet[$uid])) {
            $leg = 'right';
        } else {
            $place = strtolower(trim((string) ($row['binary_placement'] ?? '')));
            $leg = $place === 'right' ? 'right' : 'left';
        }
        if ($tab !== 'all' && $leg !== $tab) {
            continue;
        }
        $row['leg'] = $leg;
        $out[] = $row;
    }

    return arowin_collab_attach_sponsor_names($out);
}

/**
 * True if $targetUsername is a Power ID created by $rootUsername's $150+ package.
 */
function arowin_collab_is_own_power_id(string $rootUsername, string $targetUsername): bool
{
    $rootUsername = arowin_normalize_sponsor_id($rootUsername);
    $targetUsername = arowin_normalize_sponsor_id($targetUsername);
    if ($rootUsername === '' || $targetUsername === '' || $rootUsername === $targetUsername) {
        return false;
    }

    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }

    $stmt = arowin_db()->prepare(
        'SELECT power_owner FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $targetUsername]);
    $owner = arowin_normalize_sponsor_id((string) ($stmt->fetchColumn() ?: ''));

    return $owner === $rootUsername;
}

/**
 * Attach rank + income totals for collaboration table rows (page slice).
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function arowin_collab_attach_member_stats(array $rows): array
{
    if ($rows === []) {
        return [];
    }

    require_once dirname(__DIR__, 3) . '/includes/ranks-repository.php';
    require_once dirname(__DIR__, 3) . '/includes/income-repository.php';

    $usernames = [];
    foreach ($rows as $row) {
        $uid = arowin_normalize_sponsor_id((string) ($row['username'] ?? ''));
        if ($uid !== '') {
            $usernames[$uid] = true;
        }
    }
    $list = array_keys($usernames);

    $incomeMap = [];
    $rankMap = [];
    if ($list !== []) {
        $placeholders = [];
        $params = [];
        foreach ($list as $i => $uid) {
            $key = ':u' . $i;
            $placeholders[] = $key;
            $params[$key] = $uid;
        }
        try {
            if (function_exists('arowin_ensure_income_schema')) {
                arowin_ensure_income_schema();
            }
            $sql = 'SELECT UPPER(username) AS username, type, COALESCE(SUM(amount), 0) AS total
                    FROM wallet_transactions
                    WHERE UPPER(username) IN (' . implode(',', $placeholders) . ')
                      AND type IN (\'referral_income\', \'matching_income\', \'rank_bonus\', \'daily_yield\')
                    GROUP BY UPPER(username), type';
            $stmt = arowin_db()->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $inc) {
                $u = (string) ($inc['username'] ?? '');
                $t = (string) ($inc['type'] ?? '');
                if ($u === '' || $t === '') {
                    continue;
                }
                $incomeMap[$u][$t] = round((float) ($inc['total'] ?? 0), 2);
            }
        } catch (Throwable $e) {
            error_log('collab income attach: ' . $e->getMessage());
        }
        if (function_exists('arowin_rank_labels_for_usernames')) {
            $rankMap = arowin_rank_labels_for_usernames($list);
        }
    }

    foreach ($rows as &$row) {
        $uid = arowin_normalize_sponsor_id((string) ($row['username'] ?? ''));
        $referral = (float) ($incomeMap[$uid]['referral_income'] ?? 0);
        $matching = (float) ($incomeMap[$uid]['matching_income'] ?? 0);
        $rankBonus = (float) ($incomeMap[$uid]['rank_bonus'] ?? 0);
        $dailyYield = (float) ($incomeMap[$uid]['daily_yield'] ?? 0);
        $earned = round($referral + $matching + $rankBonus + $dailyYield, 2);
        $wallet = round((float) ($row['wallet_balance'] ?? 0), 2);
        // Transfer-all sends wallet to master. List must show remaining, not lifetime credits.
        if ($wallet < 0.01 || $earned < 0.01) {
            $referral = $matching = $rankBonus = $dailyYield = 0.0;
            $earned = 0.0;
        } elseif ($wallet + 0.009 < $earned) {
            $ratio = $wallet / $earned;
            $referral = round($referral * $ratio, 2);
            $matching = round($matching * $ratio, 2);
            $rankBonus = round($rankBonus * $ratio, 2);
            $dailyYield = round($dailyYield * $ratio, 2);
            $earned = round($referral + $matching + $rankBonus + $dailyYield, 2);
            $fix = round($wallet - $earned, 2);
            if ($fix !== 0.0) {
                $referral = round($referral + $fix, 2);
                $earned = $wallet;
            }
        }
        $row['rank_label'] = $rankMap[$uid] ?? 'Unranked';
        $row['referral_income'] = $referral;
        $row['matching_income'] = $matching;
        $row['rank_bonus'] = $rankBonus;
        $row['daily_yield'] = $dailyYield;
        $row['total_income'] = $earned;
    }
    unset($row);

    return $rows;
}

/**
 * Attach sponsor_name for each row's sponsor_id (batch lookup).
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function arowin_collab_attach_sponsor_names(array $rows): array
{
    if ($rows === []) {
        return [];
    }

    $ids = [];
    foreach ($rows as $row) {
        $sid = arowin_normalize_sponsor_id((string) ($row['sponsor_id'] ?? ''));
        if ($sid !== '' && (!function_exists('arowin_root_sponsor_ids') || !in_array($sid, arowin_root_sponsor_ids(), true))) {
            $ids[$sid] = true;
        }
    }

    $names = [];
    if ($ids !== []) {
        $list = array_keys($ids);
        $placeholders = [];
        $params = [];
        foreach ($list as $i => $id) {
            $key = ':s' . $i;
            $placeholders[] = $key;
            $params[$key] = $id;
        }
        $sql = 'SELECT username, full_name FROM node_registrations WHERE UPPER(username) IN ('
            . implode(',', $placeholders) . ')';
        $stmt = arowin_db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $names[strtoupper((string) $r['username'])] = trim((string) ($r['full_name'] ?? ''));
        }
    }

    foreach ($rows as &$row) {
        $sid = arowin_normalize_sponsor_id((string) ($row['sponsor_id'] ?? ''));
        $row['sponsor_name'] = $names[$sid] ?? '';
    }
    unset($row);

    return $rows;
}

/**
 * True if $targetUsername sits under $rootUsername binary left or right leg.
 */
function arowin_collab_is_downline(string $rootUsername, string $targetUsername): bool
{
    $rootUsername = arowin_normalize_sponsor_id($rootUsername);
    $targetUsername = arowin_normalize_sponsor_id($targetUsername);
    if ($rootUsername === '' || $targetUsername === '' || $rootUsername === $targetUsername) {
        return false;
    }

    foreach (['left', 'right'] as $leg) {
        foreach (arowin_list_binary_team($rootUsername, $leg) as $row) {
            if (strcasecmp((string) ($row['username'] ?? ''), $targetUsername) === 0) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Who should be restored when leaving a member dashboard.
 */
function arowin_collab_home_uid(): string
{
    return arowin_normalize_sponsor_id((string) ($_SESSION['arowin_collab_home_uid'] ?? ''));
}

function arowin_collab_is_viewing_member(): bool
{
    $home = arowin_collab_home_uid();
    $current = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    return $home !== '' && $current !== '' && $home !== $current;
}

/**
 * Restore original operator session (after viewing a member dashboard).
 *
 * @return array{ok:bool, message:string}
 */
function arowin_collab_restore_home(): array
{
    $home = arowin_collab_home_uid();
    if ($home === '') {
        return ['ok' => false, 'message' => 'No home ID to restore.'];
    }

    $user = arowin_get_user_by_username($home);
    if (!$user || (int) ($user['id'] ?? 0) < 1) {
        unset($_SESSION['arowin_collab_home_uid'], $_SESSION['arowin_collab_from_uid']);
        return ['ok' => false, 'message' => 'Home account not found.'];
    }

    arowin_login_user($user, false);
    unset($_SESSION['arowin_collab_home_uid'], $_SESSION['arowin_collab_from_uid']);

    return ['ok' => true, 'message' => 'Returned to ' . $home . '.'];
}

/**
 * Create a one-time token to open a member dashboard in a new tab.
 *
 * @return array{ok:bool, message:string, token?:string}
 */
function arowin_collab_issue_open_token(string $actorUsername, string $targetUsername): array
{
    $actorUsername = arowin_normalize_sponsor_id($actorUsername);
    $targetUsername = arowin_normalize_sponsor_id($targetUsername);

    if ($actorUsername === '' || $targetUsername === '') {
        return ['ok' => false, 'message' => 'Invalid member ID.'];
    }
    if ($actorUsername === $targetUsername) {
        return ['ok' => false, 'message' => 'You are already on this ID.'];
    }
    if (!arowin_collab_is_own_power_id($actorUsername, $targetUsername)) {
        return ['ok' => false, 'message' => 'You can only open Power IDs from your $150+ package.'];
    }

    $target = arowin_get_user_by_username($targetUsername);
    if (!$target || (int) ($target['id'] ?? 0) < 1) {
        return ['ok' => false, 'message' => 'Member account not found.'];
    }

    $token = bin2hex(random_bytes(16));
    if (!isset($_SESSION['arowin_collab_tokens']) || !is_array($_SESSION['arowin_collab_tokens'])) {
        $_SESSION['arowin_collab_tokens'] = [];
    }

    // Drop expired tokens
    $now = time();
    foreach ($_SESSION['arowin_collab_tokens'] as $k => $meta) {
        if (!is_array($meta) || (int) ($meta['exp'] ?? 0) < $now) {
            unset($_SESSION['arowin_collab_tokens'][$k]);
        }
    }

    $_SESSION['arowin_collab_tokens'][$token] = [
        'actor'  => $actorUsername,
        'target' => $targetUsername,
        'exp'    => $now + 120,
    ];

    return ['ok' => true, 'message' => 'Token ready.', 'token' => $token];
}

/**
 * Consume open token and switch session to member ID.
 *
 * @return array{ok:bool, message:string}
 */
function arowin_collab_consume_open_token(string $token): array
{
    $token = trim($token);
    if ($token === '' || empty($_SESSION['arowin_collab_tokens'][$token]) || !is_array($_SESSION['arowin_collab_tokens'][$token])) {
        return ['ok' => false, 'message' => 'Open link expired. Try Login again.'];
    }

    $meta = $_SESSION['arowin_collab_tokens'][$token];
    unset($_SESSION['arowin_collab_tokens'][$token]);

    if ((int) ($meta['exp'] ?? 0) < time()) {
        return ['ok' => false, 'message' => 'Open link expired. Try Login again.'];
    }

    $actor = arowin_normalize_sponsor_id((string) ($meta['actor'] ?? ''));
    $targetUsername = arowin_normalize_sponsor_id((string) ($meta['target'] ?? ''));
    if ($actor === '' || $targetUsername === '') {
        return ['ok' => false, 'message' => 'Invalid open link.'];
    }

    // Actor must match current session or saved home ID
    $current = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    $home = arowin_collab_home_uid();
    if ($current !== $actor && $home !== $actor) {
        return ['ok' => false, 'message' => 'Session changed. Open Login again from Power IDs.'];
    }

    if (!arowin_collab_is_own_power_id($actor, $targetUsername)) {
        return ['ok' => false, 'message' => 'You can only open Power IDs from your $150+ package.'];
    }

    $target = arowin_get_user_by_username($targetUsername);
    if (!$target || (int) ($target['id'] ?? 0) < 1) {
        return ['ok' => false, 'message' => 'Member account not found.'];
    }

    if (empty($_SESSION['arowin_collab_home_uid'])) {
        $_SESSION['arowin_collab_home_uid'] = $actor;
    }
    $_SESSION['arowin_collab_from_uid'] = $actor;

    arowin_login_user($target, false);

    return ['ok' => true, 'message' => 'Opened ' . $targetUsername . '.'];
}

/**
 * Team Collaborations lists Power IDs of the *current* account (?as=).
 * Restore the operator only when this tab is still inside a Power-ID Login view.
 */
function arowin_collab_ensure_home_session(): void
{
    // Stay on the current ID. Power IDs page is gated to the $150+ owner only;
    // child / Power ID sessions must not be switched back to the owner (that dumped the full 254 list).
}

function arowin_collab_format_date(?string $createdAt): string
{
    $createdAt = trim((string) $createdAt);
    if ($createdAt === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($createdAt))->format('d M Y');
    } catch (Throwable $e) {
        return $createdAt;
    }
}

/**
 * All owned Power IDs: count, combined earnings, spendable wallet.
 *
 * @return array{count:int, earnings:float, wallet:float}
 */
function arowin_collab_power_totals(string $owner): array
{
    $owner = arowin_normalize_sponsor_id($owner);
    $empty = ['count' => 0, 'earnings' => 0.0, 'wallet' => 0.0];
    if ($owner === '') {
        return $empty;
    }
    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }
    if (function_exists('arowin_ensure_wallet_schema')) {
        arowin_ensure_wallet_schema();
    }

    $pdo = arowin_db();
    try {
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*) AS n, COALESCE(SUM(wallet_balance), 0) AS wallet
             FROM node_registrations
             WHERE UPPER(power_owner) = :o'
        );
        $countStmt->execute([':o' => $owner]);
        $row = $countStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $count = (int) ($row['n'] ?? 0);
        $wallet = round((float) ($row['wallet'] ?? 0), 2);
    } catch (Throwable $e) {
        return $empty;
    }

    // Remaining on Power IDs (after transfer-all this is $0). Lifetime credits stay in history.
    return [
        'count' => $count,
        'earnings' => $wallet,
        'wallet' => $wallet,
    ];
}

/**
 * Owner-only: move every owned Power ID wallet balance to the master wallet.
 *
 * @return array{ok:bool, message:string, moved:float, ids:int}
 */
function arowin_collab_transfer_all_to_master(string $owner): array
{
    $owner = arowin_normalize_sponsor_id($owner);
    if ($owner === '') {
        return ['ok' => false, 'message' => 'Invalid master ID.', 'moved' => 0.0, 'ids' => 0];
    }
    $actor = arowin_get_user_by_username($owner);
    if (!$actor || !function_exists('arowin_user_can_view_power_ids') || !arowin_user_can_view_power_ids($actor)) {
        return ['ok' => false, 'message' => 'Only the Power ID master can transfer.', 'moved' => 0.0, 'ids' => 0];
    }
    if (!function_exists('arowin_wallet_debit') || !function_exists('arowin_wallet_credit')) {
        return ['ok' => false, 'message' => 'Wallet is unavailable.', 'moved' => 0.0, 'ids' => 0];
    }

    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }
    if (function_exists('arowin_ensure_wallet_schema')) {
        arowin_ensure_wallet_schema();
    }

    $pdo = arowin_db();
    $lockName = 'pidsweep_' . strtolower($owner);
    $gotLock = false;
    try {
        $lk = $pdo->prepare('SELECT GET_LOCK(:n, 8)');
        $lk->execute([':n' => $lockName]);
        $gotLock = ((int) $lk->fetchColumn() === 1);
    } catch (Throwable $e) {
        $gotLock = false;
    }
    if (!$gotLock) {
        return ['ok' => false, 'message' => 'Transfer already running. Try again.', 'moved' => 0.0, 'ids' => 0];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT username, wallet_balance
             FROM node_registrations
             WHERE UPPER(power_owner) = :o
               AND wallet_balance >= 0.01
             ORDER BY id ASC'
        );
        $stmt->execute([':o' => $owner]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return ['ok' => true, 'message' => 'No Power ID amount to send.', 'moved' => 0.0, 'ids' => 0];
        }

        $moved = 0.0;
        $ids = 0;
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        try {
            foreach ($rows as $row) {
                $pid = arowin_normalize_sponsor_id((string) ($row['username'] ?? ''));
                $amt = round((float) ($row['wallet_balance'] ?? 0), 2);
                if ($pid === '' || $amt < 0.01) {
                    continue;
                }
                $debit = arowin_wallet_debit(
                    $pid,
                    $amt,
                    'transfer_out',
                    $owner,
                    'Sent to master wallet ' . $owner
                );
                if (empty($debit['ok'])) {
                    throw new RuntimeException((string) ($debit['message'] ?? 'Debit failed for ' . $pid));
                }
                $credit = arowin_wallet_credit(
                    $owner,
                    $amt,
                    'transfer_in',
                    $pid,
                    'Power ID transfer from ' . $pid
                );
                if (empty($credit['ok'])) {
                    throw new RuntimeException((string) ($credit['message'] ?? 'Credit failed for ' . $owner));
                }
                $moved = round($moved + $amt, 2);
                $ids++;
            }
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Power ID sweep failed for ' . $owner . ': ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Transfer failed. Try again.', 'moved' => 0.0, 'ids' => 0];
        }

        return [
            'ok' => true,
            'message' => 'Sent $' . number_format($moved, 2) . ' from ' . $ids . ' Power ID'
                . ($ids === 1 ? '' : 's') . ' to master wallet.',
            'moved' => $moved,
            'ids' => $ids,
        ];
    } finally {
        try {
            $pdo->prepare('SELECT RELEASE_LOCK(:n)')->execute([':n' => $lockName]);
        } catch (Throwable $e) {
            // ignore
        }
    }
}
