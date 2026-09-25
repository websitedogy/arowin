<?php
/**
 * Welcome email + one-time dashboard open link after registration
 */
declare(strict_types=1);

function arowin_ensure_welcome_login_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    arowin_db()->exec(
        "CREATE TABLE IF NOT EXISTS `welcome_login_tokens` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `token_hash` CHAR(64) NOT NULL,
          `expires_at` DATETIME NOT NULL,
          `used_at` DATETIME DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_welcome_token` (`token_hash`),
          KEY `idx_welcome_user` (`username`),
          KEY `idx_welcome_exp` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Issue a single-use dashboard open token (raw token returned once).
 *
 * @return array{ok:bool,token?:string,url?:string,message:string}
 */
function arowin_issue_welcome_login_token(string $username, int $ttlDays = 30): array
{
    arowin_ensure_welcome_login_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Invalid user.'];
    }

    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $ttlDays = max(1, min(90, $ttlDays));
    $expires = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+' . $ttlDays . ' days')
        ->format('Y-m-d H:i:s');

    $pdo = arowin_db();
    // Invalidate unused prior tokens for this user
    $pdo->prepare(
        "UPDATE welcome_login_tokens
         SET used_at = UTC_TIMESTAMP()
         WHERE UPPER(username) = :u AND used_at IS NULL"
    )->execute([':u' => $username]);

    $pdo->prepare(
        'INSERT INTO welcome_login_tokens (username, token_hash, expires_at)
         VALUES (:u, :h, :e)'
    )->execute([
        ':u' => $username,
        ':h' => $hash,
        ':e' => $expires,
    ]);

    $url = url('welcome-login') . '?token=' . rawurlencode($raw);

    return [
        'ok' => true,
        'token' => $raw,
        'url' => $url,
        'message' => 'Token issued.',
    ];
}

/**
 * Consume one-time token and return the user row to log in.
 *
 * @return array{ok:bool,message:string,user?:array}
 */
function arowin_consume_welcome_login_token(string $rawToken): array
{
    arowin_ensure_welcome_login_schema();
    $rawToken = trim($rawToken);
    if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/i', $rawToken)) {
        return ['ok' => false, 'message' => 'Invalid or expired dashboard link.'];
    }

    $hash = hash('sha256', strtolower($rawToken));
    $pdo = arowin_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id, username, expires_at, used_at
             FROM welcome_login_tokens
             WHERE token_hash = :h
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':h' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Invalid or expired dashboard link.'];
        }
        if (!empty($row['used_at'])) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This dashboard link was already used. Please log in with your User ID and password.'];
        }

        $expiresAt = strtotime((string) $row['expires_at'] . ' UTC');
        if ($expiresAt !== false && $expiresAt < time()) {
            $pdo->prepare('UPDATE welcome_login_tokens SET used_at = UTC_TIMESTAMP() WHERE id = :id')
                ->execute([':id' => (int) $row['id']]);
            $pdo->commit();
            return ['ok' => false, 'message' => 'This dashboard link has expired. Please log in with your User ID and password.'];
        }

        $pdo->prepare('UPDATE welcome_login_tokens SET used_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute([':id' => (int) $row['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('welcome login consume: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not open dashboard. Please try logging in.'];
    }

    $user = arowin_get_user_by_username((string) $row['username']);
    if (!$user || (int) ($user['id'] ?? 0) < 1) {
        return ['ok' => false, 'message' => 'Account not found. Please contact support.'];
    }

    return ['ok' => true, 'message' => 'Welcome.', 'user' => $user];
}

/**
 * Send registration welcome email with credentials summary + dashboard open link.
 *
 * @return array{ok:bool,message:string}
 */
function arowin_send_registration_welcome_email(
    string $toEmail,
    string $fullName,
    string $userId,
    string $sponsorId,
    string $sponsorName,
    string $dashboardUrl,
    string $mobile = '',
    string $placement = ''
): array {
    if (!function_exists('arowin_send_mail')) {
        require_once __DIR__ . '/mail-service.php';
    }

    $toEmail = strtolower(trim($toEmail));
    $fullName = trim($fullName);
    $userId = arowin_normalize_sponsor_id($userId);
    $sponsorId = arowin_normalize_sponsor_id($sponsorId);
    $sponsorName = trim($sponsorName) !== '' ? trim($sponsorName) : $sponsorId;
    $mobile = trim($mobile);
    $placement = strtolower(trim($placement));
    $placementLabel = $placement === 'left'
        ? 'Left Leg (L)'
        : ($placement === 'right' ? 'Right Leg (R)' : 'Not specified');
    $registeredAt = date('d M Y, h:i A') . ' IST';
    $appName = (string) ($GLOBALS['APP']['name'] ?? 'Arowin Trading Portal');
    $support = (string) ($GLOBALS['APP']['contact_email'] ?? 'support@arowin.com');

    $subject = $appName . ' — Your User ID ' . $userId;

    $text = "Welcome to {$appName}, {$fullName}!\n\n"
        . "Your account has been created successfully.\n\n"
        . "User ID: {$userId}\n"
        . "Email: {$toEmail}\n"
        . "Mobile: {$mobile}\n"
        . "Sponsor ID: {$sponsorId}\n"
        . "Sponsor Name: {$sponsorName}\n\n"
        . "Placement: {$placementLabel}\n"
        . "Registered: {$registeredAt}\n\n"
        . "Open your dashboard (first click signs you in):\n{$dashboardUrl}\n\n"
        . "Keep your User ID and password safe. After this link is used once, log in at the Login page.\n\n"
        . "Support: {$support}\n";

    $safeName = e($fullName);
    $safeUid = e($userId);
    $safeSid = e($sponsorId);
    $safeSname = e($sponsorName);
    $safeEmail = e($toEmail);
    $safeMobile = e($mobile);
    $safePlacement = e($placementLabel);
    $safeRegisteredAt = e($registeredAt);
    $safeUrl = e($dashboardUrl);
    $safeApp = e($appName);
    $safeSupport = e($support);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>{$safeApp}</title></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Segoe UI,Arial,sans-serif;color:#111827;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
        <tr><td style="background:#0f172a;color:#fff;padding:20px 24px;font-size:18px;font-weight:700;">{$safeApp}</td></tr>
        <tr><td style="padding:24px;">
          <p style="margin:0 0 12px;font-size:16px;">Welcome, <strong>{$safeName}</strong>.</p>
          <p style="margin:0 0 18px;font-size:14px;line-height:1.5;color:#374151;">Your node account is ready. Save these details:</p>
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;border-collapse:collapse;">
            <tr>
              <td style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;width:38%;color:#6b7280;">User ID</td>
              <td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:700;">{$safeUid}</td>
            </tr>
            <tr>
              <td style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;color:#6b7280;">Sponsor ID</td>
              <td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:700;">{$safeSid}</td>
            </tr>
            <tr>
              <td style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;color:#6b7280;">Sponsor Name</td>
              <td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:700;">{$safeSname}</td>
            </tr>
                        <tr>
                            <td style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;color:#6b7280;">Email</td>
                            <td style="padding:10px 12px;border:1px solid #e5e7eb;">{$safeEmail}</td>
                        </tr>
                        <tr>
                            <td style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;color:#6b7280;">Mobile</td>
                            <td style="padding:10px 12px;border:1px solid #e5e7eb;">{$safeMobile}</td>
                        </tr>
                        <tr>
                            <td style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;color:#6b7280;">Placement</td>
                            <td style="padding:10px 12px;border:1px solid #e5e7eb;font-weight:700;">{$safePlacement}</td>
                        </tr>
                        <tr>
                            <td style="padding:10px 12px;background:#f9fafb;border:1px solid #e5e7eb;color:#6b7280;">Registered</td>
                            <td style="padding:10px 12px;border:1px solid #e5e7eb;">{$safeRegisteredAt}</td>
                        </tr>
          </table>
          <p style="margin:22px 0 10px;text-align:center;">
            <a href="{$safeUrl}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;font-size:14px;">Open Dashboard</a>
          </p>
          <p style="margin:0;font-size:12px;line-height:1.5;color:#6b7280;text-align:center;">First click signs you in automatically. This link works once.</p>
          <p style="margin:18px 0 0;font-size:12px;color:#9ca3af;">Support: {$safeSupport}</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    return arowin_send_mail([
        'to' => $toEmail,
        'subject' => $subject,
        'text' => $text,
        'html' => $html,
        'reply_to' => $support,
    ]);
}
