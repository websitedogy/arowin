<?php
/**
 * Operator security — 2FA PIN + biometric (WebAuthn credential id)
 */
declare(strict_types=1);

function arowin_ensure_security_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo = arowin_db();
    $adds = [
        'two_fa_enabled' => "ADD COLUMN `two_fa_enabled` TINYINT(1) NOT NULL DEFAULT 0",
        'two_fa_pin_hash' => "ADD COLUMN `two_fa_pin_hash` VARCHAR(255) DEFAULT NULL",
        'biometric_enabled' => "ADD COLUMN `biometric_enabled` TINYINT(1) NOT NULL DEFAULT 0",
        'webauthn_credential_id' => "ADD COLUMN `webauthn_credential_id` VARCHAR(512) DEFAULT NULL",
        'webauthn_public_key' => "ADD COLUMN `webauthn_public_key` TEXT DEFAULT NULL",
    ];

    foreach ($adds as $col => $sql) {
        try {
            $exists = $pdo->query('SHOW COLUMNS FROM node_registrations LIKE ' . $pdo->quote($col))->fetch();
            if (!$exists) {
                $pdo->exec('ALTER TABLE `node_registrations` ' . $sql);
            }
        } catch (Throwable $e) {
            error_log('security column ' . $col . ': ' . $e->getMessage());
        }
    }
}

/**
 * @return array{
 *   two_fa_enabled:bool,
 *   biometric_enabled:bool,
 *   has_pin:bool,
 *   has_webauthn:bool,
 *   webauthn_credential_id:?string,
 *   login_mode:string
 * }
 */
function arowin_security_status(string $username): array
{
    arowin_ensure_security_schema();
    $username = arowin_normalize_sponsor_id($username);
    $defaults = [
        'two_fa_enabled' => false,
        'biometric_enabled' => false,
        'has_pin' => false,
        'has_webauthn' => false,
        'webauthn_credential_id' => null,
        'login_mode' => 'signin',
    ];
    if ($username === '') {
        return $defaults;
    }

    $stmt = arowin_db()->prepare(
        'SELECT two_fa_enabled, two_fa_pin_hash, biometric_enabled, webauthn_credential_id
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $defaults;
    }

    $cred = trim((string) ($row['webauthn_credential_id'] ?? ''));
    $twoFa = (int) ($row['two_fa_enabled'] ?? 0) === 1;
    $bio = (int) ($row['biometric_enabled'] ?? 0) === 1;

    // Heal conflicting rows: only one mode may be active
    if ($bio && $twoFa) {
        arowin_security_clear_2fa($username);
        $twoFa = false;
    }

    $mode = 'signin';
    if ($bio) {
        $mode = 'biometric';
    } elseif ($twoFa) {
        $mode = '2fa';
    }

    return [
        'two_fa_enabled' => $twoFa,
        'biometric_enabled' => $bio,
        'has_pin' => trim((string) ($row['two_fa_pin_hash'] ?? '')) !== '',
        'has_webauthn' => $cred !== '',
        'webauthn_credential_id' => $cred !== '' ? $cred : null,
        'login_mode' => $mode,
    ];
}

/**
 * Clear biometric columns (no auth check — used when switching modes).
 */
function arowin_security_clear_biometric(string $username): void
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return;
    }
    arowin_db()->prepare(
        'UPDATE node_registrations
         SET biometric_enabled = 0,
             webauthn_credential_id = NULL,
             webauthn_public_key = NULL
         WHERE UPPER(username) = :u
         LIMIT 1'
    )->execute([':u' => $username]);
}

/**
 * Clear 2FA columns (no PIN check — used when switching modes while logged in).
 */
function arowin_security_clear_2fa(string $username): void
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return;
    }
    arowin_db()->prepare(
        'UPDATE node_registrations
         SET two_fa_enabled = 0, two_fa_pin_hash = NULL
         WHERE UPPER(username) = :u
         LIMIT 1'
    )->execute([':u' => $username]);
    arowin_security_remember_device($username, false);
}

/**
 * Switch to password Sign In only (disables 2FA + biometric).
 *
 * @return array{ok:bool,message:string,security?:array<string,mixed>}
 */
function arowin_security_enable_signin(string $username): array
{
    arowin_ensure_security_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }

    arowin_security_clear_2fa($username);
    arowin_security_clear_biometric($username);

    return [
        'ok' => true,
        'message' => 'Sign In mode enabled. Password login only.',
        'security' => arowin_security_status($username),
    ];
}

/**
 * @return array{ok:bool,message:string}
 */
function arowin_security_enable_2fa(string $username, string $pin, string $confirmPin): array
{
    arowin_ensure_security_schema();
    $username = arowin_normalize_sponsor_id($username);
    $pin = preg_replace('/\D+/', '', $pin) ?? '';
    $confirmPin = preg_replace('/\D+/', '', $confirmPin) ?? '';

    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }
    if (!preg_match('/^\d{6}$/', $pin)) {
        return ['ok' => false, 'message' => 'Enter a 6-digit PIN.'];
    }
    if ($pin !== $confirmPin) {
        return ['ok' => false, 'message' => 'PIN confirmation does not match.'];
    }

    $hash = password_hash($pin, PASSWORD_DEFAULT);
    // Exclusive: turn off biometric when enabling 2FA
    arowin_security_clear_biometric($username);

    $stmt = arowin_db()->prepare(
        'UPDATE node_registrations
         SET two_fa_enabled = 1, two_fa_pin_hash = :h
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':h' => $hash, ':u' => $username]);
    arowin_security_remember_device($username, true);
    return [
        'ok' => true,
        'message' => '2FA enabled. Sign In and Fingerprint are disabled.',
        'security' => arowin_security_status($username),
    ];
}

/**
 * @return array{ok:bool,message:string}
 */
function arowin_security_disable_2fa(string $username, string $pin): array
{
    arowin_ensure_security_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }
    if (!arowin_security_verify_pin($username, $pin)) {
        return ['ok' => false, 'message' => 'Incorrect security PIN.'];
    }

    $stmt = arowin_db()->prepare(
        'UPDATE node_registrations
         SET two_fa_enabled = 0, two_fa_pin_hash = NULL
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    arowin_security_remember_device($username, false);
    return [
        'ok' => true,
        'message' => '2FA disabled. Sign In mode is active.',
        'security' => arowin_security_status($username),
    ];
}

function arowin_security_verify_pin(string $username, string $pin): bool
{
    arowin_ensure_security_schema();
    $username = arowin_normalize_sponsor_id($username);
    $pin = preg_replace('/\D+/', '', $pin) ?? '';
    if ($username === '' || !preg_match('/^\d{6}$/', $pin)) {
        return false;
    }

    $stmt = arowin_db()->prepare(
        'SELECT two_fa_pin_hash FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $hash = (string) ($stmt->fetchColumn() ?: '');
    if ($hash === '') {
        return false;
    }
    return password_verify($pin, $hash);
}

/**
 * Biometric is mobile browser / mobile web app only (not desktop).
 */
function arowin_is_mobile_client(?string $userAgent = null): bool
{
    $chMobile = (string) ($_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '');
    if ($chMobile === '?1') {
        return true;
    }

    $ua = $userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') {
        return false;
    }

    return (bool) preg_match(
        '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|CriOS|FxiOS/i',
        $ua
    );
}

/**
 * @return array{ok:bool,message:string}
 */
function arowin_security_enable_biometric(string $username, string $credentialId, string $publicKey = ''): array
{
    arowin_ensure_security_schema();
    $username = arowin_normalize_sponsor_id($username);
    $credentialId = trim($credentialId);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }
    if (!arowin_is_mobile_client()) {
        return ['ok' => false, 'message' => 'Fingerprint is available only on mobile browser or the mobile web app.'];
    }
    if ($credentialId === '' || strlen($credentialId) < 16 || strlen($credentialId) > 512) {
        return ['ok' => false, 'message' => 'Invalid fingerprint credential.'];
    }
    if (!preg_match('/^[A-Za-z0-9_\-+\/=]+$/', $credentialId)) {
        return ['ok' => false, 'message' => 'Invalid fingerprint credential format.'];
    }

    // Exclusive: turn off 2FA when enabling biometric
    arowin_security_clear_2fa($username);

    $stmt = arowin_db()->prepare(
        'UPDATE node_registrations
         SET biometric_enabled = 1,
             webauthn_credential_id = :c,
             webauthn_public_key = :p
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([
        ':c' => $credentialId,
        ':p' => $publicKey !== '' ? mb_substr($publicKey, 0, 4000) : null,
        ':u' => $username,
    ]);
    return [
        'ok' => true,
        'message' => 'Fingerprint enabled. Sign In and 2FA are disabled.',
        'security' => arowin_security_status($username),
    ];
}

/**
 * @return array{ok:bool,message:string}
 */
function arowin_security_disable_biometric(string $username): array
{
    arowin_ensure_security_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }

    $stmt = arowin_db()->prepare(
        'UPDATE node_registrations
         SET biometric_enabled = 0,
             webauthn_credential_id = NULL,
             webauthn_public_key = NULL
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    return [
        'ok' => true,
        'message' => 'Fingerprint disabled. Sign In mode is active.',
        'security' => arowin_security_status($username),
    ];
}

/**
 * Public login helper — whether user has biometric registered.
 *
 * @return array{ok:bool,enabled:bool,credential_id?:string,challenge?:string,message?:string}
 */
function arowin_security_biometric_login_options(string $username): array
{
    if (!arowin_is_mobile_client()) {
        return [
            'ok' => true,
            'enabled' => false,
            'message' => 'Fingerprint login is available only on mobile.',
        ];
    }

    $status = arowin_security_status($username);
    if (!$status['biometric_enabled'] || !$status['has_webauthn'] || $status['webauthn_credential_id'] === null) {
        return ['ok' => true, 'enabled' => false];
    }

    $challenge = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $_SESSION['arowin_webauthn_challenge'] = $challenge;
    $_SESSION['arowin_webauthn_user'] = arowin_normalize_sponsor_id($username);
    $_SESSION['arowin_webauthn_at'] = time();

    return [
        'ok' => true,
        'enabled' => true,
        'credential_id' => $status['webauthn_credential_id'],
        'challenge' => $challenge,
    ];
}

/**
 * Complete biometric login after client WebAuthn assertion (credential id + challenge match).
 *
 * @return array{ok:bool,message:string,user?:array<string,mixed>,needs_2fa?:bool}
 */
function arowin_security_complete_biometric_login(string $username, string $credentialId, string $challenge): array
{
    arowin_ensure_security_schema();
    if (!arowin_is_mobile_client()) {
        return ['ok' => false, 'message' => 'Fingerprint login is available only on mobile browser or the mobile web app.'];
    }
    $username = arowin_normalize_sponsor_id($username);
    $credentialId = trim($credentialId);
    $challenge = trim($challenge);

    $sessUser = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_webauthn_user'] ?? ''));
    $sessChallenge = (string) ($_SESSION['arowin_webauthn_challenge'] ?? '');
    $sessAt = (int) ($_SESSION['arowin_webauthn_at'] ?? 0);

    unset($_SESSION['arowin_webauthn_challenge'], $_SESSION['arowin_webauthn_user'], $_SESSION['arowin_webauthn_at']);

    if ($username === '' || $credentialId === '' || $challenge === '') {
        return ['ok' => false, 'message' => 'Fingerprint verification failed.'];
    }
    if ($sessUser === '' || $sessUser !== $username || $sessChallenge === '' || !hash_equals($sessChallenge, $challenge)) {
        return ['ok' => false, 'message' => 'Fingerprint challenge expired. Try again.'];
    }
    if ($sessAt < 1 || (time() - $sessAt) > 120) {
        return ['ok' => false, 'message' => 'Fingerprint challenge expired. Try again.'];
    }

    $status = arowin_security_status($username);
    if (!$status['biometric_enabled'] || $status['webauthn_credential_id'] === null) {
        return ['ok' => false, 'message' => 'Fingerprint access is not enabled.'];
    }
    if (!hash_equals($status['webauthn_credential_id'], $credentialId)) {
        return ['ok' => false, 'message' => 'Fingerprint credential does not match.'];
    }

    $stmt = arowin_db()->prepare(
        'SELECT id, username, full_name, email, phone, sponsor_id, preferred_leg, node_package, status
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }

    $gate = arowin_user_can_sign_in($user);
    if (empty($gate['ok'])) {
        return ['ok' => false, 'message' => (string) $gate['message']];
    }

    if ($status['two_fa_enabled']) {
        // Modes are exclusive — biometric login never chains into 2FA
        arowin_security_clear_2fa($username);
    }

    arowin_login_user($user);
    return ['ok' => true, 'message' => 'Signed in with fingerprint.', 'user' => $user];
}

/**
 * After password auth — start 2FA pending session if needed.
 *
 * @param array<string,mixed> $user
 * @return array{ok:bool,needs_2fa:bool,message:string}
 */
function arowin_security_after_password(array $user): array
{
    $gate = arowin_user_can_sign_in($user);
    if (empty($gate['ok'])) {
        return ['ok' => false, 'needs_2fa' => false, 'message' => (string) $gate['message']];
    }

    $username = arowin_normalize_sponsor_id((string) ($user['username'] ?? ''));
    $status = arowin_security_status($username);
    if (!$status['two_fa_enabled']) {
        arowin_login_user($user);
        return ['ok' => true, 'needs_2fa' => false, 'message' => 'Signed in.'];
    }

    $_SESSION['arowin_2fa_pending'] = [
        'username' => $username,
        'user' => $user,
        'at' => time(),
    ];
    return ['ok' => true, 'needs_2fa' => true, 'message' => 'Enter your 6-digit security PIN.'];
}

/**
 * @return array{ok:bool,message:string}
 */
function arowin_security_complete_2fa(string $pin): array
{
    $pending = $_SESSION['arowin_2fa_pending'] ?? null;
    if (!is_array($pending)) {
        return ['ok' => false, 'message' => 'Security session expired. Sign in again.'];
    }
    $at = (int) ($pending['at'] ?? 0);
    if ($at < 1 || (time() - $at) > 600) {
        unset($_SESSION['arowin_2fa_pending']);
        return ['ok' => false, 'message' => 'Security session expired. Sign in again.'];
    }

    $username = arowin_normalize_sponsor_id((string) ($pending['username'] ?? ''));
    $user = $pending['user'] ?? null;
    if ($username === '' || !is_array($user)) {
        unset($_SESSION['arowin_2fa_pending']);
        return ['ok' => false, 'message' => 'Security session expired. Sign in again.'];
    }

    if (!arowin_security_verify_pin($username, $pin)) {
        return ['ok' => false, 'message' => 'Incorrect security PIN.'];
    }

    // Re-check block status (may have changed while PIN pending)
    $fresh = arowin_get_user_by_username($username);
    if (is_array($fresh)) {
        $user = $fresh;
    }
    $gate = arowin_user_can_sign_in(is_array($user) ? $user : []);
    if (empty($gate['ok'])) {
        unset($_SESSION['arowin_2fa_pending']);
        return ['ok' => false, 'message' => (string) $gate['message']];
    }

    unset($_SESSION['arowin_2fa_pending']);
    arowin_login_user($user);
    arowin_security_remember_device($username, true);
    return ['ok' => true, 'message' => 'Signed in.'];
}

/**
 * Start a PIN challenge for a remembered PWA user (no password step).
 *
 * @return array{ok:bool,message:string,username?:string}
 */
function arowin_security_begin_pin_challenge(string $username): array
{
    arowin_ensure_security_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'User ID is required.'];
    }

    $status = arowin_security_status($username);
    if (!$status['two_fa_enabled']) {
        return ['ok' => false, 'message' => 'Two-factor authentication is not enabled for this account.'];
    }

    $stmt = arowin_db()->prepare(
        'SELECT id, username, full_name, email, phone, sponsor_id, preferred_leg, node_package, status
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }

    $_SESSION['arowin_2fa_pending'] = [
        'username' => (string) $user['username'],
        'user' => $user,
        'at' => time(),
        'pwa_unlock' => true,
    ];

    return [
        'ok' => true,
        'message' => 'Enter your 6-digit security PIN.',
        'username' => (string) $user['username'],
    ];
}

function arowin_security_cookie_path(): string
{
    global $BASE;
    $path = '/';
    if (isset($BASE) && is_string($BASE) && $BASE !== '') {
        $parsed = parse_url($BASE, PHP_URL_PATH);
        if (is_string($parsed) && $parsed !== '') {
            $path = rtrim($parsed, '/') . '/';
        }
    }
    return $path === '//' ? '/' : $path;
}

/**
 * Remember this device for PWA PIN unlock (no password next open).
 */
function arowin_security_remember_device(string $username, bool $twoFaEnabled): void
{
    $username = arowin_normalize_sponsor_id($username);
    $path = arowin_security_cookie_path();
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    $opts = [
        'expires' => time() + 60 * 60 * 24 * 180,
        'path' => $path,
        'secure' => $secure,
        'httponly' => false,
        'samesite' => 'Lax',
    ];
    $canSet = !headers_sent();
    if ($username !== '' && $twoFaEnabled) {
        if ($canSet) {
            setcookie('arowin_pwa_uid', $username, $opts);
            setcookie('arowin_pwa_2fa', '1', $opts);
        }
        $_COOKIE['arowin_pwa_uid'] = $username;
        $_COOKIE['arowin_pwa_2fa'] = '1';
    } else {
        if ($canSet) {
            $clear = $opts;
            $clear['expires'] = time() - 3600;
            setcookie('arowin_pwa_uid', '', $clear);
            setcookie('arowin_pwa_2fa', '', $clear);
        }
        unset($_COOKIE['arowin_pwa_uid'], $_COOKIE['arowin_pwa_2fa']);
    }
}

function arowin_security_remembered_pwa_uid(): string
{
    $uid = arowin_normalize_sponsor_id((string) ($_COOKIE['arowin_pwa_uid'] ?? ''));
    if ($uid === '') {
        return '';
    }
    $status = arowin_security_status($uid);
    return !empty($status['two_fa_enabled']) ? $uid : '';
}

function arowin_security_pending_2fa_username(): string
{
    $pending = $_SESSION['arowin_2fa_pending'] ?? null;
    if (!is_array($pending)) {
        return '';
    }
    $at = (int) ($pending['at'] ?? 0);
    if ($at < 1 || (time() - $at) > 600) {
        unset($_SESSION['arowin_2fa_pending']);
        return '';
    }
    return arowin_normalize_sponsor_id((string) ($pending['username'] ?? ''));
}

function arowin_security_pending_is_pwa_unlock(): bool
{
    $pending = $_SESSION['arowin_2fa_pending'] ?? null;
    return is_array($pending) && !empty($pending['pwa_unlock']);
}
