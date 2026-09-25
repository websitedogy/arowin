<?php
/**
 * Binary left / right team listing
 */
declare(strict_types=1);

/**
 * Collect every member under root's left or right binary leg (BFS).
 *
 * @return list<array{
 *   username:string,
 *   full_name:string,
 *   phone:string,
 *   email:string,
 *   sponsor_id:string,
 *   binary_parent_id:string,
 *   binary_placement:string,
 *   node_package:string,
 *   status:string,
 *   created_at:string,
 *   level:int,
 *   profile_photo:?string
 * }>
 */
function arowin_list_binary_team(string $rootUsername, string $leg, int $maxNodes = 2000): array
{
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $rootUsername = arowin_normalize_sponsor_id($rootUsername);
    $leg = $leg === 'right' ? 'right' : 'left';
    if ($rootUsername === '') {
        return [];
    }

    $start = getBinaryTreeChild($rootUsername, $leg);
    if ($start === null) {
        return [];
    }

    $members = [];
    $queue = [[$start, 1]];
    $seen = [];

    while ($queue !== [] && count($members) < $maxNodes) {
        [$uid, $level] = array_shift($queue);
        if (isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;

        $row = arowin_team_member_row($uid, $level);
        if ($row !== null) {
            $members[] = $row;
        }

        $left = getBinaryTreeChild($uid, 'left');
        $right = getBinaryTreeChild($uid, 'right');
        if ($left !== null) {
            $queue[] = [$left, $level + 1];
        }
        if ($right !== null) {
            $queue[] = [$right, $level + 1];
        }
    }

    return $members;
}

/**
 * @return array{
 *   total:int,
 *   active:int,
 *   pending:int,
 *   members:list<array<string,mixed>>
 * }
 */
function arowin_team_page_data(string $rootUsername, string $leg): array
{
    $members = arowin_list_binary_team($rootUsername, $leg);
    $active = 0;
    $pending = 0;
    foreach ($members as $m) {
        if (($m['status'] ?? '') === 'active') {
            $active++;
        } else {
            $pending++;
        }
    }

    return [
        'total'   => count($members),
        'active'  => $active,
        'pending' => $pending,
        'members' => $members,
    ];
}

function arowin_count_binary_team(string $rootUsername, string $leg): int
{
    return (int) (arowin_binary_leg_stats($rootUsername, $leg)['count'] ?? 0);
}

/**
 * Binary leg stats: all member count + active starters + package business.
 *
 * Starters = active members with a purchased node_package (used for ranks + matching).
 * Business = sum of those package amounts.
 *
 * @return array{count:int,starters:int,business:float}
 */
function arowin_binary_leg_stats(string $rootUsername, string $leg): array
{
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $rootUsername = arowin_normalize_sponsor_id($rootUsername);
    $leg = $leg === 'right' ? 'right' : 'left';
    if ($rootUsername === '') {
        return ['count' => 0, 'starters' => 0, 'business' => 0.0];
    }

    static $cache = [];
    $cacheKey = $rootUsername . ':' . $leg;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = arowin_db()->prepare(
            "WITH RECURSIVE tree AS (
                SELECT nr.username, nr.status, nr.node_package, 1 AS depth
                FROM node_registrations nr
                WHERE UPPER(nr.binary_parent_id) = :p
                  AND nr.binary_placement = :leg
                UNION ALL
                SELECT c.username, c.status, c.node_package, tree.depth + 1
                FROM node_registrations c
                INNER JOIN tree ON UPPER(c.binary_parent_id) = UPPER(tree.username)
                WHERE tree.depth < 64
            )
            SELECT COUNT(*) AS cnt,
                   COALESCE(SUM(
                       CASE WHEN LOWER(status) = 'active'
                             AND node_package IS NOT NULL AND node_package <> ''
                             AND CAST(node_package AS CHAR) REGEXP '^[0-9]+$'
                            THEN 1 ELSE 0 END
                   ), 0) AS starters,
                   COALESCE(SUM(
                       CASE WHEN LOWER(status) = 'active'
                             AND node_package IS NOT NULL AND node_package <> ''
                             AND CAST(node_package AS CHAR) REGEXP '^[0-9]+$'
                            THEN CAST(CAST(node_package AS CHAR) AS DECIMAL(14,2)) ELSE 0 END
                   ), 0) AS business
            FROM tree"
        );
        $stmt->execute([':p' => $rootUsername, ':leg' => $leg]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $result = [
            'count' => (int) ($row['cnt'] ?? 0),
            'starters' => (int) ($row['starters'] ?? 0),
            'business' => round((float) ($row['business'] ?? 0), 2),
        ];
        $cache[$cacheKey] = $result;
        return $result;
    } catch (Throwable $e) {
        // MariaDB/MySQL without recursive CTE — fall through to BFS
    }

    $start = getBinaryTreeChild($rootUsername, $leg);
    if ($start === null) {
        $empty = ['count' => 0, 'starters' => 0, 'business' => 0.0];
        $cache[$cacheKey] = $empty;
        return $empty;
    }

    $count = 0;
    $starters = 0;
    $business = 0.0;
    $queue = [$start];
    $seen = [];
    $maxNodes = 5000;
    $stmt = arowin_db()->prepare(
        'SELECT status, node_package FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );

    while ($queue !== [] && $count < $maxNodes) {
        $uid = array_shift($queue);
        if (isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;
        $count++;

        try {
            $stmt->execute([':u' => arowin_normalize_sponsor_id((string) $uid)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $status = strtolower((string) ($row['status'] ?? ''));
                $pkg = trim((string) ($row['node_package'] ?? ''));
                $pkgAmt = 0.0;
                if ($pkg !== '' && function_exists('arowin_package_info')) {
                    $info = arowin_package_info($pkg);
                    $pkgAmt = $info ? (float) $info['amount'] : 0.0;
                } elseif ($pkg !== '' && ctype_digit($pkg)) {
                    $pkgAmt = (float) $pkg;
                }
                if ($status === 'active' && $pkgAmt > 0) {
                    $starters++;
                    $business += $pkgAmt;
                }
            }
        } catch (Throwable $e) {
            // ignore row-level lookup failures
        }

        $left = getBinaryTreeChild($uid, 'left');
        $right = getBinaryTreeChild($uid, 'right');
        if ($left !== null) {
            $queue[] = $left;
        }
        if ($right !== null) {
            $queue[] = $right;
        }
    }

    $result = [
        'count' => $count,
        'starters' => $starters,
        'business' => round($business, 2),
    ];
    $cache[$cacheKey] = $result;
    return $result;
}

function arowin_count_binary_starters(string $rootUsername, string $leg): int
{
    return (int) (arowin_binary_leg_stats($rootUsername, $leg)['starters'] ?? 0);
}

/**
 * Active packaged directs on a leg (for rank ladder).
 * Leg = preferred_leg when set, else binary placement under the sponsor.
 */
function arowin_count_direct_starters(string $sponsorUsername, string $leg): int
{
    $sponsorUsername = arowin_normalize_sponsor_id($sponsorUsername);
    $leg = $leg === 'right' ? 'right' : 'left';
    if ($sponsorUsername === '') {
        return 0;
    }

    try {
        $stmt = arowin_db()->prepare(
            'SELECT username, preferred_leg, binary_parent_id, binary_placement, status, node_package
             FROM node_registrations
             WHERE UPPER(sponsor_id) = :s'
        );
        $stmt->execute([':s' => $sponsorUsername]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return 0;
    }

    if (!function_exists('getReferralLegRelativeToSponsor')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $n = 0;
    foreach ($rows as $row) {
        $status = strtolower((string) ($row['status'] ?? ''));
        $pkg = trim((string) ($row['node_package'] ?? ''));
        if ($status !== 'active' || $pkg === '' || !ctype_digit($pkg)) {
            continue;
        }

        $pref = strtolower(trim((string) ($row['preferred_leg'] ?? '')));
        $memberLeg = '';
        if ($pref === 'left' || $pref === 'right') {
            $memberLeg = $pref;
        } else {
            $uid = arowin_normalize_sponsor_id((string) ($row['username'] ?? ''));
            if ($uid !== '') {
                $memberLeg = getReferralLegRelativeToSponsor($sponsorUsername, $uid);
            }
        }
        if ($memberLeg === $leg) {
            $n++;
        }
    }

    return $n;
}

/**
 * @return array<string,mixed>|null
 */
function arowin_team_member_row(string $username, int $level): ?array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return null;
    }

    if (function_exists('arowin_ensure_profile_schema')) {
        arowin_ensure_profile_schema();
    }

    $stmt = arowin_db()->prepare(
        'SELECT username, full_name, email, phone, sponsor_id, binary_parent_id,
                binary_placement, node_package, status, created_at, profile_photo
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $status = strtolower((string) ($row['status'] ?? 'pending'));

    return [
        'username'          => (string) $row['username'],
        'full_name'         => (string) $row['full_name'],
        'phone'             => (string) ($row['phone'] ?? ''),
        'email'             => (string) ($row['email'] ?? ''),
        'sponsor_id'        => (string) ($row['sponsor_id'] ?? ''),
        'binary_parent_id'  => (string) ($row['binary_parent_id'] ?? ''),
        'binary_placement'  => (string) ($row['binary_placement'] ?? ''),
        'node_package'      => (string) ($row['node_package'] ?? ''),
        'status'            => $status === 'active' ? 'active' : 'pending',
        'created_at'        => (string) ($row['created_at'] ?? ''),
        'level'             => $level,
        'profile_photo'     => $row['profile_photo'] ?? null,
    ];
}
