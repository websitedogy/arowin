<?php
/**
 * Operator profile — photo, password, config, login history
 */
declare(strict_types=1);

function arowin_profile_uploads_dir(): string
{
    $dir = dirname(__DIR__) . '/public/uploads/profiles';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function arowin_ensure_profile_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo = arowin_db();

    try {
        $cols = $pdo->query('SHOW COLUMNS FROM node_registrations LIKE \'profile_photo\'')->fetch();
        if (!$cols) {
            $pdo->exec(
                'ALTER TABLE `node_registrations`
                 ADD COLUMN `profile_photo` VARCHAR(255) DEFAULT NULL AFTER `usdt_wallet`'
            );
        }
    } catch (Throwable $e) {
        error_log('profile_photo column: ' . $e->getMessage());
    }

    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `login_history` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `username` VARCHAR(60) NOT NULL,
              `ip_address` VARCHAR(45) DEFAULT NULL,
              `user_agent` VARCHAR(255) DEFAULT NULL,
              `logged_in_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_login_user` (`username`),
              KEY `idx_login_at` (`logged_in_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (Throwable $e) {
        error_log('login_history table: ' . $e->getMessage());
    }
}

function arowin_profile_photo_url(?string $filename): ?string
{
    $filename = trim((string) $filename);
    if ($filename === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
        return null;
    }
    $full = arowin_profile_uploads_dir() . '/' . $filename;
    if (!is_file($full)) {
        return null;
    }
    global $BASE;
    return arowin_abs($BASE, '/public/uploads/profiles/' . rawurlencode($filename));
}

/**
 * @param array<string,mixed> $user
 */
function arowin_user_profile_photo_url(array $user): ?string
{
    $photo = $user['profile_photo'] ?? null;
    if ($photo === null || $photo === '') {
        // Lazy-load from DB if missing on row
        $uid = (string) ($user['username'] ?? $user['user_id'] ?? '');
        if ($uid !== '') {
            try {
                arowin_ensure_profile_schema();
                $stmt = arowin_db()->prepare(
                    'SELECT profile_photo FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
                );
                $stmt->execute([':u' => arowin_normalize_sponsor_id($uid)]);
                $photo = $stmt->fetchColumn() ?: null;
            } catch (Throwable $e) {
                return null;
            }
        }
    }
    return arowin_profile_photo_url($photo !== false && $photo !== null ? (string) $photo : null);
}

/**
 * @return array{ok:bool,message:string,url?:string}
 */
function arowin_upload_profile_photo(string $username, array $file): array
{
    arowin_ensure_profile_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }

    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        $errMsg = match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Photo is too large for server limits.',
            UPLOAD_ERR_PARTIAL => 'Upload was incomplete. Try again.',
            UPLOAD_ERR_NO_FILE => 'Please choose a photo to upload.',
            default => 'Upload failed (error ' . $err . ').',
        };
        return ['ok' => false, 'message' => $errMsg];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'message' => 'Invalid upload.'];
    }
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'Photo must be under 2 MB.'];
    }

    $map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
    }
    if ($mime === '' || !isset($map[$mime])) {
        $info = @getimagesize($tmp);
        if (is_array($info) && !empty($info['mime'])) {
            $mime = (string) $info['mime'];
        }
    }
    if (!isset($map[$mime])) {
        return ['ok' => false, 'message' => 'Use JPG, PNG, WEBP, or GIF.'];
    }

    $ext = $map[$mime];
    $safeUser = preg_replace('/[^A-Z0-9_-]/i', '', $username) ?: 'user';
    $filename = strtolower($safeUser) . '_' . time() . '.' . $ext;
    $destDir = arowin_profile_uploads_dir();
    if (!is_dir($destDir) || !is_writable($destDir)) {
        return ['ok' => false, 'message' => 'Upload folder is not writable.'];
    }
    $dest = $destDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        return ['ok' => false, 'message' => 'Could not save photo.'];
    }
    @chmod($dest, 0644);

    // Remove previous file
    $stmt = arowin_db()->prepare(
        'SELECT profile_photo FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $old = $stmt->fetchColumn();
    if (is_string($old) && $old !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $old)) {
        $oldPath = arowin_profile_uploads_dir() . '/' . $old;
        if (is_file($oldPath) && $old !== $filename) {
            @unlink($oldPath);
        }
    }

    $upd = arowin_db()->prepare(
        'UPDATE node_registrations SET profile_photo = :p WHERE UPPER(username) = :u LIMIT 1'
    );
    $upd->execute([':p' => $filename, ':u' => $username]);

    $url = arowin_profile_photo_url($filename);
    return [
        'ok'      => true,
        'message' => 'Profile photo updated.',
        'url'     => $url ?? '',
    ];
}

/**
 * @return array{ok:bool,message:string}
 */
function arowin_change_password(string $username, string $current, string $new, string $confirm): array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }
    if ($current === '') {
        return ['ok' => false, 'message' => 'Current password is required.'];
    }
    if (mb_strlen($new) < 6) {
        return ['ok' => false, 'message' => 'New password must be at least 6 characters.'];
    }
    if ($new !== $confirm) {
        return ['ok' => false, 'message' => 'New passwords do not match.'];
    }
    if ($current === $new) {
        return ['ok' => false, 'message' => 'New password must be different from current.'];
    }

    $stmt = arowin_db()->prepare(
        'SELECT password_hash FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($current, (string) $hash)) {
        return ['ok' => false, 'message' => 'Current password is incorrect.'];
    }

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $upd = arowin_db()->prepare(
        'UPDATE node_registrations SET password_hash = :h WHERE UPPER(username) = :u LIMIT 1'
    );
    $upd->execute([':h' => $newHash, ':u' => $username]);

    return ['ok' => true, 'message' => 'Password updated successfully.'];
}

/**
 * @param array{full_name?:string,email?:string,phone?:string,usdt_wallet?:string,country?:string} $data
 * @return array{ok:bool,message:string}
 */
function arowin_update_operator_config(string $username, array $data): array
{
    arowin_ensure_profile_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }

    $fullName = trim((string) ($data['full_name'] ?? ''));
    $email    = strtolower(trim((string) ($data['email'] ?? '')));
    $phone    = preg_replace('/\s+/', '', trim((string) ($data['phone'] ?? ''))) ?? '';
    $wallet   = trim((string) ($data['usdt_wallet'] ?? ''));
    $country  = trim((string) ($data['country'] ?? ''));

    if ($fullName === '' || mb_strlen($fullName) < 2) {
        return ['ok' => false, 'message' => 'Full name is required.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }
    if ($phone === '' || !preg_match('/^\+?[0-9]{8,15}$/', $phone)) {
        return ['ok' => false, 'message' => 'Enter a valid mobile number.'];
    }
    if ($wallet !== '' && mb_strlen($wallet) > 120) {
        return ['ok' => false, 'message' => 'USDT wallet address is too long.'];
    }
    if ($country !== '' && mb_strlen($country) > 80) {
        return ['ok' => false, 'message' => 'Country name is too long.'];
    }

    if ($email !== '') {
        $chk = arowin_db()->prepare(
            'SELECT 1 FROM node_registrations
             WHERE email = :e AND UPPER(username) <> :u
             LIMIT 1'
        );
        $chk->execute([':e' => $email, ':u' => $username]);
        if ($chk->fetchColumn()) {
            return ['ok' => false, 'message' => 'That email is already in use.'];
        }
    }

    $upd = arowin_db()->prepare(
        'UPDATE node_registrations
         SET full_name = :n,
             email = :e,
             phone = :p,
             usdt_wallet = :w,
             country = :c
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $upd->execute([
        ':n' => $fullName,
        ':e' => $email !== '' ? $email : null,
        ':p' => $phone,
        ':w' => $wallet !== '' ? $wallet : null,
        ':c' => $country !== '' ? $country : null,
        ':u' => $username,
    ]);

    $sessionUser = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($sessionUser !== '' && $sessionUser === $username) {
        $_SESSION['arowin_name']   = $fullName;
        $_SESSION['arowin_email']  = $email;
        $_SESSION['arowin_mobile'] = $phone;
    }

    return ['ok' => true, 'message' => 'Operator configuration saved.'];
}

function arowin_record_login(string $username): void
{
    try {
        arowin_ensure_profile_schema();
        $username = arowin_normalize_sponsor_id($username);
        if ($username === '') {
            return;
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $stmt = arowin_db()->prepare(
            'INSERT INTO login_history (username, ip_address, user_agent)
             VALUES (:u, :ip, :ua)'
        );
        $stmt->execute([
            ':u'  => $username, // already normalized uppercase via arowin_normalize_sponsor_id
            ':ip' => $ip !== '' ? $ip : null,
            ':ua' => $ua !== '' ? $ua : null,
        ]);
    } catch (Throwable $e) {
        error_log('record login: ' . $e->getMessage());
    }
}

/**
 * @return list<array{ip_address:?string,user_agent:?string,logged_in_at:string}>
 */
function arowin_login_history(string $username, int $limit = 10): array
{
    arowin_ensure_profile_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return [];
    }
    $limit = max(1, min(10, $limit));
    $stmt = arowin_db()->prepare(
        'SELECT ip_address, user_agent, logged_in_at
         FROM login_history
         WHERE UPPER(username) = :u
         ORDER BY logged_in_at DESC, id DESC
         LIMIT ' . $limit
    );
    $stmt->execute([':u' => $username]);
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : [];
}

/**
 * Full operator row for config form.
 *
 * @return array<string,mixed>|null
 */
function arowin_get_operator_profile(string $username): ?array
{
    arowin_ensure_profile_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return null;
    }
    $stmt = arowin_db()->prepare(
        'SELECT id, username, full_name, email, phone, sponsor_id, preferred_leg,
                node_package, usdt_wallet, country, profile_photo, status, created_at
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}
