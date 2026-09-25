<?php
/**
 * Admin — registered users listing / status / login-as
 */
declare(strict_types=1);

/**
 * UI tab → DB status.
 * inactive maps to rejected (blocked / deactivated accounts).
 */
function arowin_admin_user_tab_to_status(string $tab): string
{
    $tab = strtolower(trim($tab));
    return match ($tab) {
        'active' => 'active',
        'inactive' => 'rejected',
        'pending' => 'pending',
        default => 'all',
    };
}

function arowin_admin_user_status_label(string $status): string
{
    return match (strtolower(trim($status))) {
        'active' => 'Active',
        'rejected' => 'Blocked',
        default => 'Pending',
    };
}

function arowin_admin_user_status_badge_class(string $status): string
{
    return match (strtolower(trim($status))) {
        'active' => 'active',
        'rejected' => 'inactive',
        default => 'pending',
    };
}

/**
 * Real registrants only (exclude package power IDs).
 */
function arowin_admin_users_real_where(): string
{
    return "(power_owner IS NULL OR power_owner = '')";
}

/**
 * @return array{pending:int, active:int, inactive:int, all:int}
 */
function arowin_admin_user_status_counts(): array
{
    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }

    $counts = ['pending' => 0, 'active' => 0, 'inactive' => 0, 'all' => 0];
    $real = arowin_admin_users_real_where();
    $stmt = arowin_db()->query(
        "SELECT status, COUNT(*) AS c
         FROM node_registrations
         WHERE {$real}
         GROUP BY status"
    );
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $status = strtolower((string) ($row['status'] ?? ''));
        $n = (int) ($row['c'] ?? 0);
        $counts['all'] += $n;
        if ($status === 'active') {
            $counts['active'] += $n;
        } elseif ($status === 'rejected') {
            $counts['inactive'] += $n;
        } else {
            $counts['pending'] += $n;
        }
    }
    return $counts;
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_admin_list_users(string $tab = 'all', string $q = '', int $limit = 200): array
{
    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }
    if (function_exists('arowin_ensure_profile_schema')) {
        arowin_ensure_profile_schema();
    }

    $limit = max(1, min(1000, $limit));
    $status = arowin_admin_user_tab_to_status($tab);
    $real = arowin_admin_users_real_where();
    $params = [];
    $where = [];
    $orderBy = 'id DESC';

    if ($status !== 'all') {
        $where[] = 'status = :st';
        $params[':st'] = $status;
    }

    $q = trim($q);
    $idQ = function_exists('arowin_normalize_sponsor_id')
        ? arowin_normalize_sponsor_id($q)
        : strtoupper((string) preg_replace('/\s+/', '', $q));
    $looksLikeId = $idQ !== '' && preg_match('/^[A-Z]{2,8}[0-9]{3,}$/', $idQ) === 1;

    if ($looksLikeId) {
        // ID search = that username only (Power IDs included). Never match downlines by sponsor.
        $where[] = 'UPPER(username) LIKE :qid';
        $params[':qid'] = $idQ . '%';
        $orderBy = '(UPPER(username) <> :qidexact), id DESC';
        $params[':qidexact'] = $idQ;
    } else {
        $where[] = $real;
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(username LIKE :q1 OR full_name LIKE :q2 OR email LIKE :q3 OR phone LIKE :q4)';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }
    }

    $sql = 'SELECT id, username, full_name, email, phone, sponsor_id, preferred_leg,
                   node_package, purchased_package, power_owner, status, created_at
            FROM node_registrations
            WHERE ' . implode(' AND ', $where) . "
            ORDER BY {$orderBy}
            LIMIT {$limit}";

    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array{ok:bool, message:string}
 */
function arowin_admin_set_user_status(string $username, string $newStatus): array
{
    $username = arowin_normalize_sponsor_id($username);
    $newStatus = strtolower(trim($newStatus));
    if ($newStatus === 'inactive') {
        $newStatus = 'rejected';
    }
    if (!in_array($newStatus, ['pending', 'rejected'], true)) {
        return ['ok' => false, 'message' => 'Invalid status. Active is set only by package purchase.'];
    }
    if ($username === '') {
        return ['ok' => false, 'message' => 'Invalid user ID.'];
    }

    $guard = arowin_guard_mother_id($username, $newStatus === 'rejected' ? 'deactivated or deleted' : 'changed');
    if (empty($guard['ok'])) {
        return ['ok' => false, 'message' => (string) ($guard['message'] ?? 'Mother ID is protected.')];
    }

    $user = arowin_get_user_by_username($username);
    if (!$user || (int) ($user['id'] ?? 0) < 1) {
        return ['ok' => false, 'message' => 'User not found.'];
    }

    $powerOwner = trim((string) ($user['power_owner'] ?? ''));
    if ($powerOwner !== '') {
        return ['ok' => false, 'message' => 'Power IDs cannot be managed here.'];
    }

    $stmt = arowin_db()->prepare(
        'UPDATE node_registrations SET status = :s WHERE UPPER(username) = :u AND UPPER(username) NOT IN (\'ARWDOGY\') LIMIT 1'
    );
    $stmt->execute([':s' => $newStatus, ':u' => $username]);
    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'message' => 'Mother ID is protected and cannot be changed.'];
    }

    return [
        'ok' => true,
        'message' => $username . ' marked as ' . arowin_admin_user_status_label($newStatus)
            . ($newStatus === 'rejected' ? ' — they cannot sign in.' : '.'),
    ];
}

/**
 * Update identity details from an authenticated admin page.
 *
 * @param array<string,mixed> $data
 * @return array{ok:bool,message:string}
 */
function arowin_admin_update_user_profile(string $username, array $data): array
{
    if (!arowin_is_admin_logged_in()) {
        return ['ok' => false, 'message' => 'Admin session required.'];
    }
    $username = arowin_normalize_sponsor_id($username);
    $user = arowin_get_user_by_username($username);
    if (!$user || (int) ($user['id'] ?? 0) < 1) {
        return ['ok' => false, 'message' => 'User not found.'];
    }
    return arowin_update_operator_config($username, $data);
}

/**
 * Admin login-as: switch operator session while keeping admin session keys.
 *
 * @return array{ok:bool, message:string}
 */
function arowin_admin_login_as_user(string $username): array
{
    if (!arowin_is_admin_logged_in()) {
        return ['ok' => false, 'message' => 'Admin session required.'];
    }

    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || in_array($username, arowin_root_sponsor_ids(), true)) {
        return ['ok' => false, 'message' => 'Invalid user ID.'];
    }

    $user = arowin_get_user_by_username($username);
    if (!$user || (int) ($user['id'] ?? 0) < 1) {
        return ['ok' => false, 'message' => 'User not found.'];
    }

    arowin_login_user($user, false);
    $_SESSION['arowin_admin_impersonating'] = $username;
    $_SESSION['arowin_admin_impersonated_at'] = time();

    return ['ok' => true, 'message' => 'Logged in as ' . $username . '.'];
}

function arowin_admin_is_impersonating(): bool
{
    return arowin_is_admin_logged_in()
        && !empty($_SESSION['arowin_admin_impersonating'])
        && !empty($_SESSION['arowin_uid']);
}

function arowin_admin_clear_impersonation(): void
{
    unset($_SESSION['arowin_admin_impersonating'], $_SESSION['arowin_admin_impersonated_at']);
}
