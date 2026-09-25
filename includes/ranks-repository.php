<?php
/**
 * Binary rank ladder — Power IDs and live IDs use the same rule.
 *
 * Starter = 1 active $50 pack on Left AND 1 active $50 pack on Right.
 * Higher ranks count IDs that already qualified as Starter (not raw ID count).
 * Example: $1550 fill → 15 IDs/leg, but only 7 of those IDs have 1L:1R → Silver.
 */
declare(strict_types=1);

/**
 * Ordered low → high. Each rank needs that many active starters on BOTH legs.
 *
 * @return list<array{key:string,name:string,left:int,right:int,color:string}>
 */
function arowin_rank_ladder(): array
{
    return [
        ['key' => 'starter',           'name' => 'Starter',           'left' => 1,     'right' => 1,     'color' => '#94a3b8'],
        ['key' => 'bronze',            'name' => 'Bronze',            'left' => 3,     'right' => 3,     'color' => '#cd7f32'],
        ['key' => 'silver',            'name' => 'Silver',            'left' => 7,     'right' => 7,     'color' => '#c0c0c0'],
        ['key' => 'gold',              'name' => 'Gold',              'left' => 15,    'right' => 15,    'color' => '#f5c542'],
        ['key' => 'platina',           'name' => 'Platina',           'left' => 31,    'right' => 31,    'color' => '#e5e4e2'],
        ['key' => 'diamond',           'name' => 'Diamond',           'left' => 100,   'right' => 100,   'color' => '#7dd3fc'],
        ['key' => 'blue_sapphire',     'name' => 'Blue Sapphire',     'left' => 250,   'right' => 250,   'color' => '#3b82f6'],
        ['key' => 'ruby_elite',        'name' => 'Ruby Elite',        'left' => 500,   'right' => 500,   'color' => '#ef4444'],
        ['key' => 'emerald_crown',     'name' => 'Emerald Crown',     'left' => 1000,  'right' => 1000,  'color' => '#10b981'],
        ['key' => 'titanium_king',     'name' => 'Titanium King',     'left' => 2500,  'right' => 2500,  'color' => '#a8a29e'],
        ['key' => 'royal_legend',      'name' => 'Royal Legend',      'left' => 5000,  'right' => 5000,  'color' => '#a855f7'],
        ['key' => 'global_ambassador', 'name' => 'Global Ambassador', 'left' => 10000, 'right' => 10000, 'color' => '#ff8c1a'],
    ];
}

/**
 * Weekly rank bonus schedule (52 weeks).
 * Total = weekly × 52. Paid as weekly unlocks (not daily).
 *
 * @return list<array{key:string,name:string,weekly:float,daily:float,weeks:int,days:int,total:float}>
 */
function arowin_weekly_rank_bonus_table(): array
{
    $weeks = 52;
    $days = 364; // informational only (52 × 7)
    $weeklyByKey = [
        'starter'           => 4.0,
        'bronze'            => 6.0,
        'silver'            => 10.0,
        'gold'              => 16.0,
        'platina'           => 31.0,
        'diamond'           => 50.0,
        'blue_sapphire'     => 125.0,
        'ruby_elite'        => 250.0,
        'emerald_crown'     => 500.0,
        'titanium_king'     => 1000.0,
        'royal_legend'      => 2500.0,
        'global_ambassador' => 10000.0,
    ];

    $rows = [];
    foreach (arowin_rank_ladder() as $rank) {
        $key = (string) $rank['key'];
        $weekly = (float) ($weeklyByKey[$key] ?? 0);
        $rows[] = [
            'key' => $key,
            'name' => (string) $rank['name'],
            'color' => (string) $rank['color'],
            'weekly' => $weekly,
            'daily' => round($weekly / 7, 2), // display helper only — payout is weekly
            'weeks' => $weeks,
            'days' => $days,
            'total' => round($weekly * $weeks, 2),
        ];
    }
    return $rows;
}

function arowin_rank_requirement_label(string $rankKey, int $left, int $right): string
{
    if ($rankKey === 'starter') {
        return '1 active pack L : 1 active pack R';
    }

    return $left . ' starters L – ' . $right . ' starters R';
}

function arowin_rank_row_is_active_pack(array $row): bool
{
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    if ($status !== 'active') {
        return false;
    }
    $pkg = trim((string) ($row['node_package'] ?? ''));
    if ($pkg === '' || !preg_match('/^[0-9]+$/', $pkg)) {
        return false;
    }

    return (float) $pkg >= 50.0;
}

/**
 * Live rank metrics. Power IDs and normal IDs follow the same rule.
 *
 * packs_*     = active $50+ IDs on that binary leg (unlocks Starter)
 * starters_*  = IDs on that leg that themselves have 1 active $50 L + 1 active $50 R
 *
 * @return array{packs_left:int,packs_right:int,starters_left:int,starters_right:int}
 */
function arowin_rank_metrics(string $username): array
{
    static $cache = [];
    $username = arowin_normalize_sponsor_id($username);
    $empty = [
        'packs_left' => 0,
        'packs_right' => 0,
        'starters_left' => 0,
        'starters_right' => 0,
    ];
    if ($username === '') {
        return $empty;
    }
    if (isset($cache[$username])) {
        return $cache[$username];
    }

    try {
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $leftHead = getBinaryTreeChild($username, 'left');
    $rightHead = getBinaryTreeChild($username, 'right');
    $leftHead = $leftHead !== null ? arowin_normalize_sponsor_id($leftHead) : '';
    $rightHead = $rightHead !== null ? arowin_normalize_sponsor_id($rightHead) : '';

    $children = [];
    $queue = [];
    if ($leftHead !== '') {
        $queue[] = $leftHead;
    }
    if ($rightHead !== '') {
        $queue[] = $rightHead;
    }

    $seen = [];
    $maxNodes = 5000;
    while ($queue !== [] && count($seen) < $maxNodes) {
        $uid = arowin_normalize_sponsor_id((string) array_shift($queue));
        if ($uid === '' || isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;
        $l = getBinaryTreeChild($uid, 'left');
        $r = getBinaryTreeChild($uid, 'right');
        $l = $l !== null ? arowin_normalize_sponsor_id($l) : '';
        $r = $r !== null ? arowin_normalize_sponsor_id($r) : '';
        $children[$uid] = ['left' => $l, 'right' => $r];
        if ($l !== '') {
            $queue[] = $l;
        }
        if ($r !== '') {
            $queue[] = $r;
        }
    }

    $isPack = [];
    $ids = array_keys($seen);
    if ($ids !== []) {
        try {
            foreach (array_chunk($ids, 200) as $chunk) {
                $placeholders = [];
                $params = [];
                foreach ($chunk as $i => $uid) {
                    $key = ':u' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $uid;
                }
                $stmt = arowin_db()->prepare(
                    'SELECT username, status, node_package
                     FROM node_registrations
                     WHERE UPPER(username) IN (' . implode(',', $placeholders) . ')'
                );
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $uid = arowin_normalize_sponsor_id((string) ($row['username'] ?? ''));
                    if ($uid !== '') {
                        $isPack[$uid] = arowin_rank_row_is_active_pack($row);
                    }
                }
            }
        } catch (Throwable $e) {
            $isPack = [];
        }
    }

    $stats = [];
    $bfsOrder = array_keys($seen);
    foreach (array_reverse($bfsOrder) as $uid) {
        $l = (string) ($children[$uid]['left'] ?? '');
        $r = (string) ($children[$uid]['right'] ?? '');
        $leftPacks = $l !== '' ? (int) ($stats[$l]['subtree_packs'] ?? 0) : 0;
        $rightPacks = $r !== '' ? (int) ($stats[$r]['subtree_packs'] ?? 0) : 0;
        $leftStarters = $l !== '' ? (int) ($stats[$l]['starters_in_subtree'] ?? 0) : 0;
        $rightStarters = $r !== '' ? (int) ($stats[$r]['starters_in_subtree'] ?? 0) : 0;
        $isStarter = $leftPacks >= 1 && $rightPacks >= 1;
        $stats[$uid] = [
            'subtree_packs' => (!empty($isPack[$uid]) ? 1 : 0) + $leftPacks + $rightPacks,
            'starters_in_subtree' => ($isStarter ? 1 : 0) + $leftStarters + $rightStarters,
        ];
    }

    $leftStats = $leftHead !== '' ? ($stats[$leftHead] ?? ['subtree_packs' => 0, 'starters_in_subtree' => 0]) : [
        'subtree_packs' => 0,
        'starters_in_subtree' => 0,
    ];
    $rightStats = $rightHead !== '' ? ($stats[$rightHead] ?? ['subtree_packs' => 0, 'starters_in_subtree' => 0]) : [
        'subtree_packs' => 0,
        'starters_in_subtree' => 0,
    ];

    $result = [
        'packs_left' => (int) ($leftStats['subtree_packs'] ?? 0),
        'packs_right' => (int) ($rightStats['subtree_packs'] ?? 0),
        'starters_left' => (int) ($leftStats['starters_in_subtree'] ?? 0),
        'starters_right' => (int) ($rightStats['starters_in_subtree'] ?? 0),
    ];
    $cache[$username] = $result;
    return $result;
    } catch (Throwable $e) {
        error_log('Rank metrics failed for ' . $username . ': ' . $e->getMessage());
        $cache[$username] = $empty;
        return $empty;
    }
}

/**
 * @param array{packs_left:int,packs_right:int,starters_left:int,starters_right:int} $metrics
 * @return array{0:int,1:int}
 */
function arowin_rank_have_counts(string $rankKey, array $metrics): array
{
    if ($rankKey === 'starter') {
        return [(int) ($metrics['packs_left'] ?? 0), (int) ($metrics['packs_right'] ?? 0)];
    }

    return [(int) ($metrics['starters_left'] ?? 0), (int) ($metrics['starters_right'] ?? 0)];
}

/**
 * @return array{
 *   left:int,
 *   right:int,
 *   packs_left:int,
 *   packs_right:int,
 *   starters_left:int,
 *   starters_right:int,
 *   count_kind:string,
 *   current:?array{key:string,name:string,left:int,right:int,color:string},
 *   next:?array{key:string,name:string,left:int,right:int,color:string},
 *   rank_label:string,
 *   progress_pct:float,
 *   left_needed:int,
 *   right_needed:int,
 *   pairs:int,
 *   ladder:list<array{
 *     key:string,name:string,left:int,right:int,color:string,
 *     achieved:bool,current:bool,locked:bool,
 *     left_ok:bool,right_ok:bool,pct:float,
 *     have_left:int,have_right:int,need_label:string
 *   }>
 * }
 */
function arowin_rank_progress(string $username): array
{
    $username = arowin_normalize_sponsor_id($username);
    $metrics = arowin_rank_metrics($username);

    if (function_exists('arowin_record_rank_achievements')) {
        try {
            arowin_record_rank_achievements($username);
        } catch (Throwable $e) {
            error_log('Rank progress sync failed for ' . $username . ': ' . $e->getMessage());
        }
    }

    $ladder = arowin_rank_ladder();
    $achievedKeys = arowin_user_achieved_rank_keys($username);
    $current = null;
    $currentIdx = -1;

    foreach ($ladder as $i => $rank) {
        if (isset($achievedKeys[$rank['key']])) {
            $current = $rank;
            $currentIdx = $i;
        }
    }

    $next = $ladder[$currentIdx + 1] ?? null;
    $rankLabel = $current['name'] ?? 'Unranked';
    $focusKey = (string) (($next['key'] ?? null) ?? ($current['key'] ?? 'starter'));
    [$left, $right] = arowin_rank_have_counts($focusKey, $metrics);
    $countKind = $focusKey === 'starter' ? 'packs' : 'starters';
    $pairs = min((int) $metrics['packs_left'], (int) $metrics['packs_right']);

    $rows = [];
    foreach ($ladder as $i => $rank) {
        $key = (string) $rank['key'];
        [$haveL, $haveR] = arowin_rank_have_counts($key, $metrics);
        $needL = (int) $rank['left'];
        $needR = (int) $rank['right'];
        $leftOk = $haveL >= $needL;
        $rightOk = $haveR >= $needR;
        $isAchieved = isset($achievedKeys[$key]);
        $need = max($needL, $needR, 1);
        $pct = min(100.0, (min($haveL, $needL) / $need) * 50.0 + (min($haveR, $needR) / $need) * 50.0);

        $rows[] = [
            'key'        => $key,
            'name'       => $rank['name'],
            'left'       => $needL,
            'right'      => $needR,
            'color'      => $rank['color'],
            'achieved'   => $isAchieved,
            'current'    => $currentIdx === $i,
            'locked'     => !$isAchieved && ($currentIdx + 1) !== $i && $i > $currentIdx + 1,
            'left_ok'    => $leftOk,
            'right_ok'   => $rightOk,
            'pct'        => round($pct, 1),
            'have_left'  => $haveL,
            'have_right' => $haveR,
            'need_label' => arowin_rank_requirement_label($key, $needL, $needR),
        ];
    }

    $leftNeeded = 0;
    $rightNeeded = 0;
    $progressPct = 100.0;
    if ($next !== null) {
        [$haveL, $haveR] = arowin_rank_have_counts((string) $next['key'], $metrics);
        $leftNeeded = max(0, (int) $next['left'] - $haveL);
        $rightNeeded = max(0, (int) $next['right'] - $haveR);
        $needL = max(1, (int) $next['left']);
        $needR = max(1, (int) $next['right']);
        $progressPct = round(
            (min($haveL, (int) $next['left']) / $needL) * 50.0 +
            (min($haveR, (int) $next['right']) / $needR) * 50.0,
            1
        );
        $left = $haveL;
        $right = $haveR;
    }

    return [
        'left'           => $left,
        'right'          => $right,
        'packs_left'     => (int) $metrics['packs_left'],
        'packs_right'    => (int) $metrics['packs_right'],
        'starters_left'  => (int) $metrics['starters_left'],
        'starters_right' => (int) $metrics['starters_right'],
        'count_kind'     => $countKind,
        'current'        => $current,
        'next'           => $next,
        'rank_label'     => $rankLabel,
        'progress_pct'   => $progressPct,
        'left_needed'    => $leftNeeded,
        'right_needed'   => $rightNeeded,
        'pairs'          => $pairs,
        'ladder'       => $rows,
    ];
}

function arowin_user_rank_label(string $username): string
{
    if ($username === '') {
        return 'Unranked';
    }
    // Keep achievements in sync so navbar/dashboard never show stale ranks.
    if (function_exists('arowin_record_rank_achievements')) {
        try {
            arowin_record_rank_achievements($username);
        } catch (Throwable $e) {
            error_log('Rank label sync failed for ' . $username . ': ' . $e->getMessage());
        }
    }
    $achieved = arowin_user_highest_achieved_rank($username);
    return $achieved['rank_label'] ?? 'Unranked';
}

/**
 * @return array<string, true> rank_key => true
 */
function arowin_user_achieved_rank_keys(string $username): array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || !function_exists('arowin_ensure_rank_achievements_schema')) {
        return [];
    }
    arowin_ensure_rank_achievements_schema();

    try {
        $stmt = arowin_db()->prepare(
            'SELECT rank_key FROM rank_achievements WHERE UPPER(username) = :u'
        );
        $stmt->execute([':u' => $username]);
        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = (string) ($row['rank_key'] ?? '');
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
        return $keys;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Highest rank that was recorded as achieved.
 *
 * @return array{rank_key:string,rank_label:string}|null
 */
function arowin_user_highest_achieved_rank(string $username): ?array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return null;
    }

    if (!function_exists('arowin_ensure_rank_achievements_schema')) {
        return null;
    }
    arowin_ensure_rank_achievements_schema();

    try {
        $stmt = arowin_db()->prepare(
            'SELECT rank_key, rank_label
             FROM rank_achievements
             WHERE UPPER(username) = :u'
        );
        $stmt->execute([':u' => $username]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return null;
    }

    if ($rows === []) {
        return null;
    }

    $byKey = [];
    foreach ($rows as $row) {
        $key = (string) ($row['rank_key'] ?? '');
        if ($key !== '') {
            $byKey[$key] = (string) ($row['rank_label'] ?? $key);
        }
    }

    $best = null;
    foreach (arowin_rank_ladder() as $rank) {
        $key = (string) $rank['key'];
        if (isset($byKey[$key])) {
            $best = [
                'rank_key'   => $key,
                'rank_label' => $byKey[$key] !== '' ? $byKey[$key] : (string) $rank['name'],
            ];
        }
    }

    return $best;
}

/**
 * Highest recorded rank labels for many IDs (no tree walk / no rank recording).
 *
 * @param list<string> $usernames
 * @return array<string, string> UPPER(username) => rank label
 */
function arowin_rank_labels_for_usernames(array $usernames): array
{
    $ids = [];
    foreach ($usernames as $uid) {
        $uid = arowin_normalize_sponsor_id((string) $uid);
        if ($uid !== '') {
            $ids[$uid] = true;
        }
    }
    $list = array_keys($ids);
    if ($list === [] || !function_exists('arowin_ensure_rank_achievements_schema')) {
        return [];
    }
    arowin_ensure_rank_achievements_schema();

    $placeholders = [];
    $params = [];
    foreach ($list as $i => $uid) {
        $key = ':u' . $i;
        $placeholders[] = $key;
        $params[$key] = $uid;
    }

    try {
        $stmt = arowin_db()->prepare(
            'SELECT UPPER(username) AS username, rank_key, rank_label
             FROM rank_achievements
             WHERE UPPER(username) IN (' . implode(',', $placeholders) . ')'
        );
        $stmt->execute($params);
        $byUser = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $u = (string) ($row['username'] ?? '');
            $key = (string) ($row['rank_key'] ?? '');
            if ($u === '' || $key === '') {
                continue;
            }
            $byUser[$u][$key] = (string) ($row['rank_label'] ?? $key);
        }
    } catch (Throwable $e) {
        return [];
    }

    $out = [];
    $ladder = function_exists('arowin_rank_ladder') ? arowin_rank_ladder() : [];
    foreach ($byUser as $u => $byKey) {
        $best = 'Unranked';
        foreach ($ladder as $rank) {
            $key = (string) ($rank['key'] ?? '');
            if ($key !== '' && isset($byKey[$key])) {
                $best = $byKey[$key] !== '' ? $byKey[$key] : (string) ($rank['name'] ?? $key);
            }
        }
        $out[$u] = $best;
    }
    return $out;
}

/**
 * Live eligibility label from rank metrics (Starter uses packs, higher uses starters).
 */
function arowin_rank_label_from_metrics(array $metrics): string
{
    $current = null;
    foreach (arowin_rank_ladder() as $rank) {
        $key = (string) $rank['key'];
        [$haveL, $haveR] = arowin_rank_have_counts($key, $metrics);
        if ($haveL >= (int) $rank['left'] && $haveR >= (int) $rank['right']) {
            $current = $rank;
        }
    }
    return $current['name'] ?? 'Unranked';
}

/** @deprecated use arowin_rank_label_from_metrics */
function arowin_rank_label_from_counts(int $left, int $right): string
{
    $current = null;
    foreach (arowin_rank_ladder() as $rank) {
        if ($left >= (int) $rank['left'] && $right >= (int) $rank['right']) {
            $current = $rank;
        }
    }
    return $current['name'] ?? 'Unranked';
}
