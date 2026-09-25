<?php
/**
 * Binary genealogy helpers (BBM-compatible API for genealogy_tree.php)
 */
declare(strict_types=1);

function genealogy_h($s): string
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function requireAuth(): void
{
    if (!arowin_is_logged_in()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . url('login'), true, 303);
        exit;
    }
}

function bbm_login_url(): string
{
    return url('login');
}

/**
 * Session user mapped to genealogy shape (user_id = username).
 *
 * @return array<string, mixed>|null
 */
function getCurrentUser(): ?array
{
    $uid = (string) ($_SESSION['arowin_uid'] ?? '');
    if ($uid === '') {
        return null;
    }
    return getUserByUserId($uid);
}

/**
 * @return array<string, mixed>|null
 */
function getUserByUserId(string $userId): ?array
{
    $userId = arowin_normalize_sponsor_id($userId);
    if ($userId === '') {
        return null;
    }

    try {
        $stmt = arowin_db()->prepare(
            'SELECT id, username, full_name, email, phone, sponsor_id, preferred_leg,
                    binary_parent_id, binary_placement, node_package, purchased_package,
                    power_owner, status, created_at
             FROM node_registrations
             WHERE UPPER(username) = :u
             LIMIT 1'
        );
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        $stmt = arowin_db()->prepare(
            'SELECT id, username, full_name, email, phone, sponsor_id, preferred_leg,
                    binary_parent_id, binary_placement, node_package, status, created_at
             FROM node_registrations
             WHERE UPPER(username) = :u
             LIMIT 1'
        );
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
    }
    if (!$row) {
        return null;
    }

    return arowin_map_user_row_for_genealogy($row);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function arowin_map_user_row_for_genealogy(array $row): array
{
    $status = strtolower((string) ($row['status'] ?? 'pending'));
    $mappedStatus = match ($status) {
        'active' => 'active',
        'rejected', 'blocked', 'inactive' => 'blocked',
        default => 'pending',
    };

    $nodePkg = trim((string) ($row['node_package'] ?? ''));
    $purchased = trim((string) ($row['purchased_package'] ?? ''));
    $pkgCode = $purchased !== '' && ctype_digit($purchased) ? $purchased : $nodePkg;
    $powerOwner = arowin_normalize_sponsor_id((string) ($row['power_owner'] ?? ''));

    return [
        'id'                => (int) $row['id'],
        'user_id'           => (string) $row['username'],
        'username'          => (string) $row['username'],
        'full_name'         => (string) $row['full_name'],
        'email'             => $row['email'] ?? null,
        'mobile'            => (string) ($row['phone'] ?? ''),
        'phone'             => (string) ($row['phone'] ?? ''),
        'sponsor_id'        => (string) ($row['sponsor_id'] ?? ''),
        'preferred_leg'     => (string) ($row['preferred_leg'] ?? 'auto'),
        'binary_parent_id'  => $row['binary_parent_id'] !== null && $row['binary_parent_id'] !== ''
            ? (string) $row['binary_parent_id']
            : null,
        'binary_placement'  => $row['binary_placement'] !== null && $row['binary_placement'] !== ''
            ? (string) $row['binary_placement']
            : null,
        'node_package'      => $nodePkg,
        'purchased_package' => $purchased,
        'power_owner'       => $powerOwner,
        'is_power_id'       => $powerOwner !== '',
        'package_amount'    => (int) $pkgCode,
        'status'            => $mappedStatus,
        'created_at'        => (string) ($row['created_at'] ?? date('Y-m-d H:i:s')),
    ];
}

function invalidateBinaryTreeIndex(): void
{
    // No cache layer yet
}

/**
 * True if $member is $root or sits in $root's binary downline.
 */
function arowin_binary_is_under(string $root, string $member): bool
{
    $root = arowin_normalize_sponsor_id($root);
    $member = arowin_normalize_sponsor_id($member);
    if ($root === '' || $member === '') {
        return false;
    }
    if ($root === $member) {
        return true;
    }

    $current = $member;
    $seen = [$current => true];
    $stmt = arowin_db()->prepare(
        'SELECT binary_parent_id FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    for ($i = 0; $i < 64; $i++) {
        $stmt->execute([':u' => $current]);
        $parent = arowin_normalize_sponsor_id((string) ($stmt->fetchColumn() ?: ''));
        if ($parent === '') {
            return false;
        }
        if ($parent === $root) {
            return true;
        }
        if (isset($seen[$parent])) {
            return false;
        }
        $seen[$parent] = true;
        $current = $parent;
    }
    return false;
}

/**
 * @return string|null child username
 */
function getBinaryTreeChild(string $parentId, string $leg): ?string
{
    $parentId = arowin_normalize_sponsor_id($parentId);
    $leg = $leg === 'right' ? 'right' : 'left';
    if ($parentId === '') {
        return null;
    }

    $stmt = arowin_db()->prepare(
        'SELECT username FROM node_registrations
         WHERE UPPER(binary_parent_id) = :p AND binary_placement = :leg
         ORDER BY id ASC
         LIMIT 1'
    );
    $stmt->execute([':p' => $parentId, ':leg' => $leg]);
    $child = $stmt->fetchColumn();
    return $child ? (string) $child : null;
}

/**
 * Find extreme empty slot under sponsor on a leg.
 *
 * @return array{parent:string,leg:string}|null
 */
function arowin_find_extreme_binary_slot(string $sponsorId, string $leg): ?array
{
    $sponsorId = arowin_normalize_sponsor_id($sponsorId);
    $leg = $leg === 'right' ? 'right' : 'left';
    if ($sponsorId === '') {
        return null;
    }
    if (!getUserByUserId($sponsorId)) {
        return null;
    }

    $current = $sponsorId;
    for ($i = 0; $i < 128; $i++) {
        $child = getBinaryTreeChild($current, $leg);
        if ($child === null) {
            return ['parent' => $current, 'leg' => $leg];
        }
        $current = $child;
    }

    return null;
}

function arowin_set_binary_placement(string $username, string $parentId, string $leg): bool
{
    $username = arowin_normalize_sponsor_id($username);
    $parentId = arowin_normalize_sponsor_id($parentId);
    $leg = $leg === 'right' ? 'right' : 'left';

    $stmt = arowin_db()->prepare(
        'UPDATE node_registrations
         SET binary_parent_id = :p, binary_placement = :leg
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    return $stmt->execute([':p' => $parentId, ':leg' => $leg, ':u' => $username]);
}

function executeQuery(string $sql, array $params = [], string $types = ''): bool
{
    $sql = str_replace(
        ['UPDATE users SET', 'WHERE user_id ='],
        ['UPDATE node_registrations SET', 'WHERE username ='],
        $sql
    );
    $stmt = arowin_db()->prepare($sql);
    return $stmt->execute($params);
}

function generateUserId(): string
{
    return arowin_generate_user_id();
}

function userExistsByEmail(string $email): bool
{
    return arowin_email_exists($email);
}

function isMobileRegistrationAllowed(string $mobile): bool
{
    return arowin_is_mobile_registration_allowed($mobile);
}

function bbmMaxIdsPerMobile(): int
{
    return arowin_max_ids_per_mobile();
}

/**
 * @param array{user_id:string,sponsor_id:string,placement:string,full_name:string,mobile:string,email:string,password:string} $data
 */
function createUser(array $data): bool
{
    $user = arowin_create_registered_user($data);
    return $user !== null;
}

/**
 * @return array<string, array<string, mixed>>
 */
function buildGenealogyTreeData(string $rootId, int $maxDepth = 64): array
{
    $rootId = arowin_normalize_sponsor_id($rootId);
    $root = getUserByUserId($rootId);
    if (!$root) {
        return [];
    }

    $tree = [];
    $queue = [[$rootId, 0]];
    $seen = [];
    $maxNodes = 3000;

    while ($queue !== []) {
        [$uid, $depth] = array_shift($queue);
        if (isset($seen[$uid]) || $depth > $maxDepth) {
            continue;
        }
        $seen[$uid] = true;
        if (count($tree) >= $maxNodes) {
            break;
        }

        $user = getUserByUserId($uid);
        if (!$user) {
            continue;
        }

        $left = getBinaryTreeChild($uid, 'left');
        $right = getBinaryTreeChild($uid, 'right');

        $pkgAmt = (int) ($user['package_amount'] ?? 0);
        $status = (string) ($user['status'] ?? 'pending');
        $selfBv = ($status === 'active' && $pkgAmt > 0) ? $pkgAmt : 0;

        $tree[$uid] = [
            'id'            => $uid,
            'name'          => (string) $user['full_name'],
            'status'        => $status,
            'joinDt'        => date('d/m/Y', strtotime((string) $user['created_at'])),
            'referral'      => (string) ($user['sponsor_id'] ?? ''),
            'mobile'        => (string) ($user['mobile'] ?? ''),
            'rank'          => 'Unranked',
            'package'       => $pkgAmt,
            'packageAmount' => $pkgAmt,
            'packageColor'  => '',
            'selfBv'        => $selfBv,
            'leftBusiness'  => 0.0,
            'rightBusiness' => 0.0,
            'leftChild'     => $left,
            'rightChild'    => $right,
            'isPowerId'     => !empty($user['is_power_id']),
            'powerOwner'    => (string) ($user['power_owner'] ?? ''),
        ];

        if ($left) {
            $queue[] = [$left, $depth + 1];
        }
        if ($right) {
            $queue[] = [$right, $depth + 1];
        }
    }

    return $tree;
}

/**
 * Fill package business (selfBv), left/right leg business, and achieved rank label.
 */
function enrichGenealogyTreeWithSelfBv(array &$treeData): void
{
    if (!function_exists('arowin_binary_leg_stats')) {
        require_once __DIR__ . '/team-repository.php';
    }
    if (!function_exists('arowin_user_rank_label')) {
        require_once __DIR__ . '/ranks-repository.php';
    }

    foreach ($treeData as $uid => &$node) {
        $amt = (int) ($node['packageAmount'] ?? $node['package'] ?? 0);
        $st = strtolower((string) ($node['status'] ?? ''));
        $node['selfBv'] = ($st === 'active' && $amt > 0) ? $amt : 0;

        $leftStats = arowin_binary_leg_stats((string) $uid, 'left');
        $rightStats = arowin_binary_leg_stats((string) $uid, 'right');
        $node['leftBusiness'] = (float) ($leftStats['business'] ?? 0);
        $node['rightBusiness'] = (float) ($rightStats['business'] ?? 0);
        // Display only ranks that were recorded as achieved.
        $node['rank'] = arowin_user_rank_label((string) $uid);
    }
    unset($node);
}

function enrichGenealogyTreeWithPackageColors(array &$treeData): void
{
    // Kept for backward compatibility — tree node color is status-based, not package tier.
    foreach ($treeData as &$node) {
        $st = strtolower((string) ($node['status'] ?? 'pending'));
        $node['packageColor'] = match ($st) {
            'active' => '#22c55e',
            'blocked', 'rejected', 'inactive' => '#ef4444',
            default => '#ff8c1a',
        };
    }
    unset($node);
}

/**
 * @return array{left: array{total:int,active:int,inactive:int,blocked:int}, right: array{total:int,active:int,inactive:int,blocked:int}}
 */
function getBinaryGenealogyTeamStats(string $rootId): array
{
    $empty = ['total' => 0, 'active' => 0, 'inactive' => 0, 'blocked' => 0];
    $stats = ['left' => $empty, 'right' => $empty];
    $tree = buildGenealogyTreeData($rootId);
    if ($tree === [] || !isset($tree[$rootId])) {
        return $stats;
    }

    foreach (['left' => $tree[$rootId]['leftChild'] ?? null, 'right' => $tree[$rootId]['rightChild'] ?? null] as $leg => $child) {
        if (!$child) {
            continue;
        }
        $stack = [(string) $child];
        $visited = [];
        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($visited[$id]) || !isset($tree[$id])) {
                continue;
            }
            $visited[$id] = true;
            $stats[$leg]['total']++;
            $st = strtolower((string) ($tree[$id]['status'] ?? 'pending'));
            if ($st === 'active') {
                $stats[$leg]['active']++;
            } elseif (in_array($st, ['blocked', 'rejected', 'inactive'], true)) {
                $stats[$leg]['blocked']++;
            } else {
                // pending (+ unknown) → inactive bucket for existing UI keys
                $stats[$leg]['inactive']++;
            }
            if (!empty($tree[$id]['leftChild'])) {
                $stack[] = (string) $tree[$id]['leftChild'];
            }
            if (!empty($tree[$id]['rightChild'])) {
                $stack[] = (string) $tree[$id]['rightChild'];
            }
        }
    }

    return $stats;
}

function getReferralLegRelativeToSponsor(string $sponsorId, string $memberId): string
{
    $sponsorId = arowin_normalize_sponsor_id($sponsorId);
    $memberId = arowin_normalize_sponsor_id($memberId);
    $current = getUserByUserId($memberId);
    for ($i = 0; $i < 64 && $current; $i++) {
        $parent = (string) ($current['binary_parent_id'] ?? '');
        $leg = (string) ($current['binary_placement'] ?? '');
        if ($parent === $sponsorId) {
            return $leg === 'right' ? 'right' : 'left';
        }
        if ($parent === '') {
            break;
        }
        $current = getUserByUserId($parent);
    }
    return 'left';
}

function getRootUser(): ?array
{
    $stmt = arowin_db()->query(
        'SELECT id, username, full_name, email, phone, sponsor_id, preferred_leg,
                binary_parent_id, binary_placement, node_package, status, created_at
         FROM node_registrations
         ORDER BY id ASC
         LIMIT 1'
    );
    $row = $stmt ? $stmt->fetch() : false;
    return $row ? arowin_map_user_row_for_genealogy($row) : null;
}

/**
 * @return array{left:int,right:int}
 */
function getDeduplicatedTeamLegCounts(string $userId): array
{
    $stats = getBinaryGenealogyTeamStats($userId);
    return [
        'left'  => (int) $stats['left']['total'],
        'right' => (int) $stats['right']['total'],
    ];
}

function getTeamCount(string $userId): int
{
    $userId = arowin_normalize_sponsor_id($userId);
    $stmt = arowin_db()->prepare(
        'SELECT COUNT(*) FROM node_registrations WHERE UPPER(sponsor_id) = :s'
    );
    $stmt->execute([':s' => $userId]);
    return (int) $stmt->fetchColumn();
}

function ensureUsersProfilePhotoColumn(): void
{
    if (function_exists('arowin_ensure_profile_schema')) {
        arowin_ensure_profile_schema();
    }
}

function getProfilePhotoWebPath(array $user): ?string
{
    if (function_exists('arowin_user_profile_photo_url')) {
        return arowin_user_profile_photo_url($user);
    }
    return null;
}

function applyMemberHeaderRankVars(array $user): void
{
}

/**
 * Backfill binary placement for rows that only have sponsor + preferred_leg.
 */
function arowin_backfill_binary_tree(): void
{
    $stmt = arowin_db()->query(
        'SELECT username, sponsor_id, preferred_leg, binary_parent_id
         FROM node_registrations
         ORDER BY id ASC'
    );
    if (!$stmt) {
        return;
    }

    while ($row = $stmt->fetch()) {
        if (!empty($row['binary_parent_id'])) {
            continue;
        }
        $sponsor = arowin_normalize_sponsor_id((string) ($row['sponsor_id'] ?? ''));
        $leg = (string) ($row['preferred_leg'] ?? '');
        if ($leg !== 'left' && $leg !== 'right') {
            $leg = 'left';
        }
        $username = (string) $row['username'];

        if ($sponsor === '') {
            continue;
        }
        if (!getUserByUserId($sponsor)) {
            continue;
        }

        $slot = arowin_find_extreme_binary_slot($sponsor, $leg);
        if ($slot) {
            arowin_set_binary_placement($username, $slot['parent'], $slot['leg']);
        }
    }
}
