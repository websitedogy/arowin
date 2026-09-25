<?php
/**
 * Forgot password — issue a new password and email credentials
 */
declare(strict_types=1);

/**
 * Generate a readable temporary password (10 chars).
 */
function arowin_generate_temp_password(int $length = 10): string
{
    $length = max(8, min(24, $length));
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

/**
 * Verify User ID + email, reset password, email credentials.
 *
 * @return array{ok:bool,message:string,sent?:bool}
 */
function arowin_forgot_password_reset(string $username, string $email): array
{
    $username = arowin_normalize_sponsor_id($username);
    $email = strtolower(trim($email));

    $genericOk = 'If those details match an account, login credentials were sent to that email.';

    if ($username === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid User ID and registered email.'];
    }

    // Simple session rate limit
    $now = time();
    $bucket = $_SESSION['arowin_forgot_pw'] ?? ['n' => 0, 'at' => $now];
    if (!is_array($bucket)) {
        $bucket = ['n' => 0, 'at' => $now];
    }
    if (($now - (int) ($bucket['at'] ?? 0)) > 900) {
        $bucket = ['n' => 0, 'at' => $now];
    }
    if ((int) ($bucket['n'] ?? 0) >= 5) {
        return ['ok' => false, 'message' => 'Too many attempts. Please wait a few minutes and try again.'];
    }
    $bucket['n'] = (int) ($bucket['n'] ?? 0) + 1;
    $bucket['at'] = (int) ($bucket['at'] ?? $now);
    $_SESSION['arowin_forgot_pw'] = $bucket;

    $stmt = arowin_db()->prepare(
        'SELECT id, username, full_name, email, password_hash, power_owner
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Same response whether mismatch / missing — avoid account enumeration
    if (
        !$row
        || strtolower(trim((string) ($row['email'] ?? ''))) !== $email
        || empty($row['password_hash'])
    ) {
        return ['ok' => true, 'message' => $genericOk, 'sent' => false];
    }

    $newPassword = arowin_generate_temp_password(10);
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $oldHash = (string) $row['password_hash'];

    $upd = arowin_db()->prepare(
        'UPDATE node_registrations SET password_hash = :h WHERE id = :id LIMIT 1'
    );
    $upd->execute([
        ':h' => $newHash,
        ':id' => (int) $row['id'],
    ]);
    if ($upd->rowCount() < 1) {
        return ['ok' => false, 'message' => 'Could not reset password. Please try again.'];
    }

    require_once __DIR__ . '/mail-service.php';
    $mail = arowin_send_forgot_password_credentials_email(
        $email,
        (string) ($row['full_name'] ?? $username),
        (string) $row['username'],
        $newPassword
    );

    if (empty($mail['ok'])) {
        // Restore previous password if mail failed
        arowin_db()->prepare(
            'UPDATE node_registrations SET password_hash = :h WHERE id = :id LIMIT 1'
        )->execute([
            ':h' => $oldHash,
            ':id' => (int) $row['id'],
        ]);
        error_log('Forgot password email failed for ' . $username . ': ' . ($mail['message'] ?? ''));
        return [
            'ok' => false,
            'message' => 'Could not send email right now. Please try again later or contact support.',
            'sent' => false,
        ];
    }

    return ['ok' => true, 'message' => $genericOk, 'sent' => true];
}

/**
 * @return array{ok:bool,message:string}
 */
function arowin_send_forgot_password_credentials_email(
    string $toEmail,
    string $fullName,
    string $userId,
    string $password
): array {
    require_once __DIR__ . '/mail-service.php';

    $toEmail = strtolower(trim($toEmail));
    $fullName = trim($fullName);
    $userId = arowin_normalize_sponsor_id($userId);
    $appName = (string) ($GLOBALS['APP']['name'] ?? 'Arowin Trading Portal');
    $support = (string) ($GLOBALS['APP']['contact_email'] ?? 'support@arowin.com');
    $loginUrl = function_exists('url') ? url('login') : '';

    $subject = $appName . ' — Your login credentials';

    $text = "Hi {$fullName},\n\n"
        . "You requested your Arowin login credentials. A new password was generated for your account.\n\n"
        . "User ID: {$userId}\n"
        . "Password: {$password}\n\n"
        . ($loginUrl !== '' ? "Login: {$loginUrl}\n\n" : '')
        . "For security, change this password after you sign in (Profile → Change password).\n\n"
        . "If you did not request this, contact support immediately: {$support}\n";

    $safeName = htmlspecialchars($fullName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeUid = htmlspecialchars($userId, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safePw = htmlspecialchars($password, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeApp = htmlspecialchars($appName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeSupport = htmlspecialchars($support, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeLogin = htmlspecialchars($loginUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $cta = $loginUrl !== ''
        ? '<p style="margin:22px 0 8px;text-align:center;"><a href="' . $safeLogin . '" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;font-size:14px;">Open Login</a></p>'
        : '';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>{$safeApp}</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Segoe UI,Arial,sans-serif;color:#111827;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
        <tr><td style="background:#0f172a;color:#fff;padding:20px 24px;font-size:18px;font-weight:700;">{$safeApp}</td></tr>
        <tr><td style="padding:24px;">
          <p style="margin:0 0 12px;font-size:16px;">Hi <strong>{$safeName}</strong>,</p>
          <p style="margin:0 0 18px;font-size:14px;line-height:1.5;color:#374151;">You requested your login credentials. A <strong>new password</strong> was generated for your account.</p>
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;border-collapse:collapse;">
            <tr><td style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;width:38%;color:#6b7280;">User ID</td><td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:700;">{$safeUid}</td></tr>
            <tr><td style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;color:#6b7280;">Password</td><td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:700;font-family:Consolas,monospace;">{$safePw}</td></tr>
          </table>
          {$cta}
          <p style="margin:14px 0 0;font-size:12px;line-height:1.5;color:#6b7280;">Change this password after you sign in (Profile → Change password).</p>
          <p style="margin:18px 0 0;font-size:12px;color:#9ca3af;">If you did not request this, contact {$safeSupport} immediately.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;

    return arowin_send_mail([
        'to' => $toEmail,
        'subject' => $subject,
        'text' => $text,
        'html' => $html,
        'reply_to' => $support,
    ]);
}

/**
 * Admin function to reset user password and email new credentials
 *
 * @return array{ok:bool,message:string,password?:string}
 */
function arowin_admin_reset_user_password(string $username): array
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Invalid user ID.'];
    }

    $stmt = arowin_db()->prepare(
        'SELECT id, username, full_name, email, password_hash
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['ok' => false, 'message' => 'User not found.'];
    }

    if (empty($row['email'])) {
        return ['ok' => false, 'message' => 'User has no email address. Cannot send password.'];
    }

    $newPassword = arowin_generate_temp_password(10);
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $oldHash = (string) $row['password_hash'];

    $upd = arowin_db()->prepare(
        'UPDATE node_registrations SET password_hash = :h WHERE id = :id LIMIT 1'
    );
    $upd->execute([
        ':h' => $newHash,
        ':id' => (int) $row['id'],
    ]);
    if ($upd->rowCount() < 1) {
        return ['ok' => false, 'message' => 'Could not reset password. Please try again.'];
    }

    require_once __DIR__ . '/mail-service.php';
    $mail = arowin_send_forgot_password_credentials_email(
        (string) $row['email'],
        (string) ($row['full_name'] ?? $username),
        (string) $row['username'],
        $newPassword
    );

    if (empty($mail['ok'])) {
        // Restore previous password if mail failed
        arowin_db()->prepare(
            'UPDATE node_registrations SET password_hash = :h WHERE id = :id LIMIT 1'
        )->execute([
            ':h' => $oldHash,
            ':id' => (int) $row['id'],
        ]);
        error_log('Admin password reset email failed for ' . $username . ': ' . ($mail['message'] ?? ''));
        return [
            'ok' => false,
            'message' => 'Could not send email right now. Please try again later.',
        ];
    }

    return [
        'ok' => true,
        'message' => 'Password reset and emailed to ' . $row['email'],
        'password' => $newPassword,
    ];
}
