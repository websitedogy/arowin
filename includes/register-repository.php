<?php
/**
 * Node registration repository
 */
declare(strict_types=1);

/** Company / mother root ID (login account; also valid as first sponsor) */
function arowin_root_sponsor_ids(): array
{
    return ['ARWDOGY'];
}

function arowin_is_mother_id(string $username): bool
{
    $username = arowin_normalize_sponsor_id($username);
    return $username !== '' && in_array($username, arowin_root_sponsor_ids(), true);
}

/**
 * Mother ID must never be deleted / blocked / demoted.
 *
 * @return array{ok:bool, message?:string}
 */
function arowin_guard_mother_id(string $username, string $action = 'modify'): array
{
    if (!arowin_is_mother_id($username)) {
        return ['ok' => true];
    }
    return [
        'ok' => false,
        'message' => 'Mother ID (' . arowin_normalize_sponsor_id($username) . ') is permanent and cannot be ' . $action . '.',
    ];
}

/**
 * Hard-delete a registrant. Mother ID is always refused.
 *
 * @return array{ok:bool, message:string}
 */
function arowin_delete_user_account(string $username): array
{
    $username = arowin_normalize_sponsor_id($username);
    $guard = arowin_guard_mother_id($username, 'deleted');
    if (empty($guard['ok'])) {
        return ['ok' => false, 'message' => (string) ($guard['message'] ?? 'Protected account.')];
    }
    if ($username === '') {
        return ['ok' => false, 'message' => 'Invalid user ID.'];
    }

    try {
        $stmt = arowin_db()->prepare(
            'DELETE FROM node_registrations WHERE UPPER(username) = :u AND UPPER(username) NOT IN (\'ARWDOGY\') LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        if ($stmt->rowCount() < 1) {
            return ['ok' => false, 'message' => 'User not found or protected.'];
        }
        return ['ok' => true, 'message' => $username . ' deleted.'];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => env('APP_DEBUG', false) ? $e->getMessage() : 'Unable to delete user.',
        ];
    }
}

function arowin_max_ids_per_mobile(): int
{
    // 0 = unlimited (default). Set AROWIN_MAX_IDS_PER_MOBILE>0 to cap.
    $n = (int) env('AROWIN_MAX_IDS_PER_MOBILE', 0);
    return $n > 0 ? $n : 0;
}

/**
 * Drop unique email constraint so many User IDs can share one email.
 */
function arowin_ensure_shared_contact_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo = arowin_db();
        $idx = $pdo->query("SHOW INDEX FROM `node_registrations` WHERE Key_name = 'uq_reg_email'")->fetch();
        if ($idx) {
            $pdo->exec('ALTER TABLE `node_registrations` DROP INDEX `uq_reg_email`');
        }
        // Non-unique lookup index (ignore if already exists)
        try {
            $pdo->exec('ALTER TABLE `node_registrations` ADD INDEX `idx_reg_email` (`email`)');
        } catch (Throwable $e) {
            // already exists
        }
    } catch (Throwable $e) {
        error_log('shared contact schema: ' . $e->getMessage());
    }
}

function arowin_normalize_sponsor_id(string $sponsorId): string
{
    $id = strtoupper(trim($sponsorId));
    $id = preg_replace('/\s+/', '', $id) ?? $id;
    return $id;
}

/**
 * @return array{id:int,username:string,full_name:string,email:?string,phone:?string}|null
 */
function arowin_get_user_by_username(string $username): ?array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return null;
    }

    if (function_exists('arowin_ensure_profile_schema')) {
        arowin_ensure_profile_schema();
    }

    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }

    $stmt = arowin_db()->prepare(
        'SELECT id, username, full_name, email, phone, sponsor_id, preferred_leg,
                binary_parent_id, binary_placement, node_package,
                purchased_package, power_owner, usdt_wallet, country, profile_photo, status, created_at
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }

    // Fallback only if mother ID row is missing (pre-seed / recovery)
    if (in_array($username, arowin_root_sponsor_ids(), true)) {
        return [
            'id'        => 0,
            'username'  => $username,
            'full_name' => 'Arowin Mother Node',
            'email'     => null,
            'phone'     => null,
        ];
    }

    return null;
}

function arowin_username_exists(string $username): bool
{
    $stmt = arowin_db()->prepare('SELECT 1 FROM node_registrations WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    return (bool) $stmt->fetchColumn();
}

function arowin_email_exists(string $email): bool
{
    arowin_ensure_shared_contact_schema();
    $email = strtolower(trim($email));
    if ($email === '') {
        return false;
    }
    $stmt = arowin_db()->prepare('SELECT 1 FROM node_registrations WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    return (bool) $stmt->fetchColumn();
}

function arowin_is_mobile_registration_allowed(string $mobile): bool
{
    arowin_ensure_shared_contact_schema();
    $max = arowin_max_ids_per_mobile();
    if ($max <= 0) {
        return true; // unlimited IDs per mobile
    }
    if (function_exists('arowin_ensure_power_schema')) {
        arowin_ensure_power_schema();
    }
    // Power/auto IDs do not consume the public mobile ID quota
    $stmt = arowin_db()->prepare(
        'SELECT COUNT(*) FROM node_registrations
         WHERE phone = :p AND (power_owner IS NULL OR power_owner = \'\')'
    );
    $stmt->execute([':p' => $mobile]);
    return (int) $stmt->fetchColumn() < $max;
}

function arowin_generate_user_id(): string
{
    // Format: ARW + 8 digits  e.g. ARW48291736 (random, non-sequential)
    for ($i = 0; $i < 200; $i++) {
        $n = str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $candidate = 'ARW' . $n;
        if (!arowin_username_exists($candidate)) {
            return $candidate;
        }
    }

    // Extremely unlikely fallback
    return 'ARW' . str_pad((string) (time() % 100000000), 8, '0', STR_PAD_LEFT);
}

/**
 * Place new user under sponsor at extreme left/right slot.
 */
function arowin_place_in_binary_tree(string $sponsorUsername, string $placement, string $newUsername): bool
{
    if (!in_array($placement, ['left', 'right'], true)) {
        return false;
    }

    $sponsorUsername = arowin_normalize_sponsor_id($sponsorUsername);
    $newUsername = arowin_normalize_sponsor_id($newUsername);

    // Empty sponsor — cannot place
    if ($sponsorUsername === '') {
        return true;
    }

    // Mother ID with a real DB row: place new members under that account
    if (in_array($sponsorUsername, arowin_root_sponsor_ids(), true) && !arowin_username_exists($sponsorUsername)) {
        return true;
    }

    if (!function_exists('arowin_find_extreme_binary_slot')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $slot = arowin_find_extreme_binary_slot($sponsorUsername, $placement);
    if (!$slot) {
        return false;
    }

    return arowin_set_binary_placement($newUsername, $slot['parent'], $slot['leg']);
}

function arowin_save_node_registration(array $data): int
{
    $sql = 'INSERT INTO node_registrations
        (full_name, email, phone, username, password_hash, sponsor_id, preferred_leg, node_package, usdt_wallet, country, consent, ip_address, user_agent)
        VALUES
        (:full_name, :email, :phone, :username, :password_hash, :sponsor_id, :preferred_leg, :node_package, :usdt_wallet, :country, :consent, :ip_address, :user_agent)';

    $email = isset($data['email']) ? trim((string) $data['email']) : '';
    $email = $email !== '' ? strtolower($email) : null;

    $pkg = trim((string) ($data['node_package'] ?? ''));
    if ($pkg === '' || !function_exists('arowin_is_valid_package') || !arowin_is_valid_package($pkg)) {
        $pkg = null;
    }

    $stmt = arowin_db()->prepare($sql);
    $stmt->execute([
        ':full_name'     => $data['full_name'],
        ':email'         => $email,
        ':phone'         => $data['phone'],
        ':username'      => $data['username'],
        ':password_hash' => $data['password_hash'],
        ':sponsor_id'    => $data['sponsor_id'] ?: null,
        ':preferred_leg' => $data['preferred_leg'] ?? 'auto',
        ':node_package'  => $pkg,
        ':usdt_wallet'   => $data['usdt_wallet'] ?: null,
        ':country'       => $data['country'] ?: null,
        ':consent'       => !empty($data['consent']) ? 1 : 0,
        ':ip_address'    => $data['ip_address'] ?? null,
        ':user_agent'    => $data['user_agent'] ?? null,
    ]);

    return (int) arowin_db()->lastInsertId();
}

/**
 * @param array{user_id:string,sponsor_id:string,placement:string,full_name:string,mobile:string,email:string,password:string} $payload
 */
function arowin_create_registered_user(array $payload): ?array
{
    arowin_ensure_shared_contact_schema();
    $id = arowin_save_node_registration([
        'full_name'     => $payload['full_name'],
        'email'         => $payload['email'] ?? '',
        'phone'         => $payload['mobile'],
        'username'      => $payload['user_id'],
        'password_hash' => $payload['password'],
        'sponsor_id'    => $payload['sponsor_id'],
        'preferred_leg' => $payload['placement'],
        'node_package'  => $payload['node_package'] ?? null,
        'usdt_wallet'   => null,
        'country'       => null,
        'consent'       => true,
        'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent'    => isset($_SERVER['HTTP_USER_AGENT'])
            ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
            : null,
    ]);

    if ($id < 1) {
        return null;
    }

    return arowin_get_user_by_username($payload['user_id']);
}

/**
 * @return 'left'|'right'|''
 */
function arowin_normalize_placement_leg(?string $leg): string
{
    $leg = strtolower(trim((string) $leg));
    if ($leg === 'left' || $leg === 'right') {
        return $leg;
    }
    return '';
}

/**
 * Public register URL for copy/share. Always opens the register form (never dashboard).
 * The sender's User ID is pre-filled as sponsor; optional left/right placement is locked.
 */
function arowin_join_share_url(string $sponsorId, string $leg = ''): string
{
    $sponsorId = arowin_normalize_sponsor_id($sponsorId);
    $leg = arowin_normalize_placement_leg($leg);
    $base = url('register');
    // Strip accidental multi-tab ?as= so invitees never land on a member dashboard.
    $base = preg_replace('/([?&])as=[^&]*/i', '$1', $base) ?? $base;
    $base = preg_replace('/\?&+/', '?', $base) ?? $base;
    $base = preg_replace('/&&+/', '&', $base) ?? $base;
    $base = rtrim($base, '?&');
    if ($sponsorId === '') {
        return $base;
    }
    $query = [
        'sponsor_id' => $sponsorId,
        'for_guest'  => '1',
    ];
    if ($leg !== '') {
        $query['placement'] = $leg;
    }
    $sep = str_contains($base, '?') ? '&' : '?';
    return $base . $sep . http_build_query($query);
}
