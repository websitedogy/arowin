<?php
/**
 * Direct Referrals — helpers
 */
declare(strict_types=1);

/**
 * Load one member row for the Direct Referrals table.
 *
 * @return array<string,mixed>|null
 */
function arowin_direct_referral_row_from_username(string $username): ?array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return null;
    }
    $stmt = arowin_db()->prepare(
        'SELECT username, full_name, email, phone, sponsor_id, preferred_leg,
                binary_parent_id, binary_placement, node_package, purchased_package,
                power_owner, status, created_at
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function arowin_direct_referral_normalize_row(array $row, string $forcedLeg = ''): array
{
    $status = strtolower((string) ($row['status'] ?? 'pending'));
    $leg = $forcedLeg !== ''
        ? $forcedLeg
        : strtolower((string) ($row['preferred_leg'] ?? $row['binary_placement'] ?? ''));
    if ($leg !== 'left' && $leg !== 'right') {
        $leg = '';
    }

    return [
        'username'          => (string) ($row['username'] ?? ''),
        'full_name'         => (string) ($row['full_name'] ?? ''),
        'email'             => (string) ($row['email'] ?? ''),
        'phone'             => (string) ($row['phone'] ?? ''),
        'sponsor_id'        => (string) ($row['sponsor_id'] ?? ''),
        'preferred_leg'     => $leg,
        'binary_parent_id'  => (string) ($row['binary_parent_id'] ?? ''),
        'binary_placement'  => (string) ($row['binary_placement'] ?? ''),
        'node_package'      => (string) ($row['node_package'] ?? ''),
        'purchased_package' => (string) ($row['purchased_package'] ?? ''),
        'power_owner'       => (string) ($row['power_owner'] ?? ''),
        'is_power_id'       => trim((string) ($row['power_owner'] ?? '')) !== '',
        'status'            => $status === 'active' ? 'active' : ($status === 'rejected' ? 'blocked' : 'pending'),
        'created_at'        => (string) ($row['created_at'] ?? ''),
    ];
}

/**
 * Direct Referrals list: live members who registered with this sponsor_id
 * (share-link / register sponsor). Placement parent is not a direct referral.
 * Power IDs stay on the Power IDs page.
 *
 * @return list<array<string,mixed>>
 */
function arowin_list_direct_referrals(string $sponsorUsername, int $limit = 500): array
{
    $sponsorUsername = arowin_normalize_sponsor_id($sponsorUsername);
    if ($sponsorUsername === '') {
        return [];
    }
    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }

    $limit = max(1, min(2000, $limit));
    $stmt = arowin_db()->prepare(
        "SELECT username, full_name, email, phone, sponsor_id, preferred_leg,
                binary_parent_id, binary_placement, node_package, purchased_package,
                power_owner, status, created_at
         FROM node_registrations
         WHERE UPPER(sponsor_id) = :s
           AND (power_owner IS NULL OR power_owner = '')
         ORDER BY id DESC
         LIMIT {$limit}"
    );
    $stmt->execute([':s' => $sponsorUsername]);

    $out = [];
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $uid = arowin_normalize_sponsor_id((string) ($row['username'] ?? ''));
        if ($uid === '' || isset($seen[$uid])) {
            continue;
        }
        $seen[$uid] = true;
        $out[] = arowin_direct_referral_normalize_row($row);
    }

    return $out;
}

/**
 * Immediate 1 Left : 1 Right children under this ID (live or Power ID).
 *
 * @return list<array<string,mixed>>
 */
function arowin_list_lr_direct_team(string $username): array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return [];
    }
    if (!function_exists('getBinaryTreeChild')) {
        require_once dirname(__DIR__, 3) . '/includes/genealogy-helper.php';
    }

    $out = [];
    $seen = [];
    foreach (['left', 'right'] as $leg) {
        $child = getBinaryTreeChild($username, $leg);
        if ($child === null || $child === '') {
            continue;
        }
        $uid = arowin_normalize_sponsor_id((string) $child);
        if ($uid === '' || isset($seen[$uid])) {
            continue;
        }
        $row = arowin_direct_referral_row_from_username($uid);
        if (!$row) {
            continue;
        }
        $seen[$uid] = true;
        $out[] = arowin_direct_referral_normalize_row($row, $leg);
    }

    return $out;
}

/**
 * @param list<array<string,mixed>> $rows
 * @return array{total:int, active:int, pending:int, blocked:int, left:int, right:int}
 */
function arowin_direct_referral_stats(array $rows): array
{
    $stats = ['total' => count($rows), 'active' => 0, 'pending' => 0, 'blocked' => 0, 'left' => 0, 'right' => 0];
    foreach ($rows as $row) {
        $s = (string) ($row['status'] ?? 'pending');
        if ($s === 'active') {
            $stats['active']++;
        } elseif ($s === 'blocked') {
            $stats['blocked']++;
        } else {
            $stats['pending']++;
        }
        $leg = strtolower((string) ($row['preferred_leg'] ?? ''));
        if ($leg === 'left') {
            $stats['left']++;
        } elseif ($leg === 'right') {
            $stats['right']++;
        }
    }
    return $stats;
}

function arowin_direct_referral_format_date(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($raw))->format('d M Y');
    } catch (Throwable $e) {
        return $raw;
    }
}
