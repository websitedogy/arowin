<?php
/**
 * Auth helpers — session login / logout for node operators
 * Supports multi-account bag so different tabs can use different IDs (via ?as=).
 */
declare(strict_types=1);

/**
 * Blocked / deactivated accounts (admin Deactivate → DB status rejected).
 */
function arowin_user_is_blocked(array $user): bool
{
    return strtolower(trim((string) ($user['status'] ?? ''))) === 'rejected';
}

/**
 * Member-facing sign-in gate. Admin "login as" may still open blocked accounts.
 *
 * @return array{ok:bool, message:string}
 */
function arowin_user_can_sign_in(array $user): array
{
    if (arowin_user_is_blocked($user)) {
        return [
            'ok' => false,
            'message' => 'This account has been blocked. You cannot sign in. Contact support if you need help.',
        ];
    }
    return ['ok' => true, 'message' => ''];
}

/**
 * @return array{id:int,username:string,full_name:string,email:?string,phone:?string,sponsor_id:?string,preferred_leg:?string,node_package:?string,status:?string}|null
 */
function arowin_authenticate(string $username, string $password): ?array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || $password === '') {
        return null;
    }

    $stmt = arowin_db()->prepare(
        'SELECT id, username, full_name, email, phone, sponsor_id, preferred_leg, node_package, status, password_hash
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();

    if (!$row || empty($row['password_hash'])) {
        return null;
    }

    if (!password_verify($password, (string) $row['password_hash'])) {
        return null;
    }

    unset($row['password_hash']);
    return $row;
}

/**
 * @return array<string, array{id:int,username:string,full_name:string,email:string,phone:string,login_at:int}>
 */
function arowin_accounts_bag(): array
{
    $bag = $_SESSION['arowin_accounts'] ?? null;
    if (!is_array($bag)) {
        $bag = [];
    }

    // Migrate legacy single-slot session into the bag once.
    $legacyUid = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($legacyUid !== '' && !isset($bag[$legacyUid])) {
        $bag[$legacyUid] = [
            'id'        => (int) ($_SESSION['arowin_user_id'] ?? 0),
            'username'  => $legacyUid,
            'full_name' => (string) ($_SESSION['arowin_name'] ?? ''),
            'email'     => (string) ($_SESSION['arowin_email'] ?? ''),
            'phone'     => (string) ($_SESSION['arowin_mobile'] ?? ''),
            'login_at'  => (int) ($_SESSION['arowin_login_at'] ?? time()),
        ];
        $_SESSION['arowin_accounts'] = $bag;
    }

    return $bag;
}

/**
 * Sync display session keys from a bag entry (or clear if none).
 */
function arowin_account_sync_active(?string $uid): void
{
    $uid = $uid !== null ? arowin_normalize_sponsor_id($uid) : '';
    $bag = arowin_accounts_bag();

    if ($uid === '' || !isset($bag[$uid])) {
        unset(
            $_SESSION['arowin_uid'],
            $_SESSION['arowin_user_id'],
            $_SESSION['arowin_name'],
            $_SESSION['arowin_email'],
            $_SESSION['arowin_mobile'],
            $_SESSION['arowin_login_at']
        );
        return;
    }

    $entry = $bag[$uid];
    $_SESSION['arowin_uid']      = (string) ($entry['username'] ?? $uid);
    $_SESSION['arowin_user_id']  = (int) ($entry['id'] ?? 0);
    $_SESSION['arowin_name']     = (string) ($entry['full_name'] ?? '');
    $_SESSION['arowin_email']    = (string) ($entry['email'] ?? '');
    $_SESSION['arowin_mobile']   = (string) ($entry['phone'] ?? '');
    $_SESSION['arowin_login_at'] = (int) ($entry['login_at'] ?? time());
}

/**
 * Activate an account already present in the session bag.
 */
function arowin_account_activate(string $uid): bool
{
    $uid = arowin_normalize_sponsor_id($uid);
    $bag = arowin_accounts_bag();
    if ($uid === '' || !isset($bag[$uid])) {
        return false;
    }
    arowin_account_sync_active($uid);
    return true;
}

/**
 * Resolve which account this request wants (?as= / POST as / X-Arowin-As).
 * Returns a bag username or null when absent/invalid.
 */
function arowin_resolve_request_account(): ?string
{
    $raw = '';
    if (isset($_GET['as'])) {
        $raw = (string) $_GET['as'];
    } elseif (isset($_POST['as'])) {
        $raw = (string) $_POST['as'];
    } else {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                if (strtolower((string) $k) === 'x-arowin-as') {
                    $raw = (string) $v;
                    break;
                }
            }
        }
        if ($raw === '' && isset($_SERVER['HTTP_X_AROWIN_AS'])) {
            $raw = (string) $_SERVER['HTTP_X_AROWIN_AS'];
        }
    }

    $uid = arowin_normalize_sponsor_id($raw);
    if ($uid === '') {
        return null;
    }
    $bag = arowin_accounts_bag();
    return isset($bag[$uid]) ? $uid : null;
}

/**
 * Apply request account context (call after session_start + bootstrap).
 * Returns the active username (may be last-active when as= missing).
 */
function arowin_apply_request_account(): string
{
    $as = arowin_resolve_request_account();
    if ($as !== null) {
        arowin_account_activate($as);
        return $as;
    }

    // No valid as= — keep last-active if still in bag; else first bag entry.
    $bag = arowin_accounts_bag();
    $current = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($current !== '' && isset($bag[$current])) {
        arowin_account_sync_active($current);
        return $current;
    }
    if ($bag !== []) {
        $first = (string) array_key_first($bag);
        arowin_account_activate($first);
        return $first;
    }

    arowin_account_sync_active(null);
    return '';
}

function arowin_login_user(array $user, bool $recordHistory = true): void
{
    $username = arowin_normalize_sponsor_id((string) ($user['username'] ?? ''));
    if ($username === '') {
        return;
    }

    if (!isset($_SESSION['arowin_accounts']) || !is_array($_SESSION['arowin_accounts'])) {
        $_SESSION['arowin_accounts'] = [];
    }

    $_SESSION['arowin_accounts'][$username] = [
        'id'        => (int) ($user['id'] ?? 0),
        'username'  => $username,
        'full_name' => (string) ($user['full_name'] ?? ''),
        'email'     => (string) ($user['email'] ?? ''),
        'phone'     => (string) ($user['phone'] ?? ''),
        'login_at'  => time(),
    ];

    arowin_account_sync_active($username);

    // Password / normal login must not keep admin "login as" banner state.
    // Admin login-as re-sets these flags immediately after calling this helper.
    unset($_SESSION['arowin_admin_impersonating'], $_SESSION['arowin_admin_impersonated_at']);

    if ($recordHistory && function_exists('arowin_record_login')) {
        arowin_record_login($username);
    }
}

/**
 * Log out one account (default: current) or wipe member session when bag empties.
 * Pass '*' to clear every member account.
 *
 * @return bool True when no member accounts remain (full member logout).
 */
function arowin_logout_user(?string $uid = null): bool
{
    $bag = arowin_accounts_bag();

    if ($uid === '*') {
        $bag = [];
    } else {
        if ($uid === null || $uid === '') {
            $uid = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
            if ($uid === '') {
                $as = arowin_resolve_request_account();
                $uid = $as ?? '';
            }
        } else {
            $uid = arowin_normalize_sponsor_id($uid);
        }
        if ($uid !== '') {
            unset($bag[$uid]);
        }
    }

    $_SESSION['arowin_accounts'] = $bag;

    if ($bag === []) {
        unset(
            $_SESSION['arowin_uid'],
            $_SESSION['arowin_user_id'],
            $_SESSION['arowin_name'],
            $_SESSION['arowin_email'],
            $_SESSION['arowin_mobile'],
            $_SESSION['arowin_login_at'],
            $_SESSION['arowin_flash'],
            $_SESSION['arowin_collab_home_uid'],
            $_SESSION['arowin_collab_from_uid'],
            $_SESSION['arowin_collab_flash_err'],
            $_SESSION['arowin_collab_tokens'],
            $_SESSION['arowin_admin_impersonating'],
            $_SESSION['arowin_admin_impersonated_at'],
            $_SESSION['arowin_2fa_pending'],
            $_SESSION['arowin_webauthn_challenge'],
            $_SESSION['arowin_webauthn_user'],
            $_SESSION['arowin_webauthn_at'],
            $_SESSION['arowin_accounts']
        );
        return true;
    }

    // Keep another account active for other tabs.
    $next = (string) array_key_first($bag);
    arowin_account_sync_active($next);
    return false;
}

function arowin_is_logged_in(): bool
{
    if (!empty($_SESSION['arowin_uid'])) {
        return true;
    }
    $bag = $_SESSION['arowin_accounts'] ?? null;
    return is_array($bag) && $bag !== [];
}

/**
 * Append ?as= / &as= for the active member account (multi-tab isolation).
 */
function arowin_url_with_account(string $url): string
{
    $uid = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($uid === '' || $url === '') {
        return $url;
    }
    if (preg_match('/(?:\?|&)as=/i', $url)) {
        return $url;
    }

    $hash = '';
    $base = $url;
    if (str_contains($url, '#')) {
        [$base, $hash] = explode('#', $url, 2);
        $hash = '#' . $hash;
    }

    $sep = str_contains($base, '?') ? '&' : '?';
    return $base . $sep . 'as=' . rawurlencode($uid) . $hash;
}

/**
 * Hidden form field so POSTs keep the tab's account context.
 */
function arowin_account_hidden_field(): string
{
    $uid = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($uid === '') {
        return '';
    }
    return '<input type="hidden" name="as" value="' . htmlspecialchars($uid, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Emit per-tab account binder script (call before </body> on dashboard pages).
 */
function arowin_print_tab_account_boot(): void
{
    global $BASE;
    $uid = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    $bag = arowin_accounts_bag();
    $ids = array_keys($bag);
    $rel = '/dashboard/assets/js/tab-account.js';
    $full = dirname(__DIR__) . $rel;
    $href = function_exists('arowin_abs')
        ? arowin_abs((string) ($BASE ?? ''), $rel)
        : $rel;
    if (is_file($full)) {
        $href .= '?v=' . filemtime($full);
    }
    echo '<script>window.AROWIN_BOOT_AS=' . json_encode($uid, JSON_UNESCAPED_SLASHES)
        . ';window.AROWIN_ACCOUNT_IDS=' . json_encode(array_values($ids), JSON_UNESCAPED_SLASHES)
        . ';</script>' . "\n";
    echo '<script src="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
}
