<?php

declare(strict_types=1);

namespace Arowin\Modules\Credentials;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PDO;
use RuntimeException;

final class CredentialDeliveryService
{
    private const LINK_LIFETIME_SECONDS = 86400;
    private const SENDER_EMAIL = 'arowintradin@gmail.com';

    private readonly string $encryptionKey;
    private readonly string $frontendUrl;

    public function __construct(private readonly PDO $database)
    {
        $configuredKey = getenv('AROWIN_CREDENTIAL_ENCRYPTION_KEY');
        $key = is_string($configuredKey) ? base64_decode($configuredKey, true) : false;
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException(
                'Set AROWIN_CREDENTIAL_ENCRYPTION_KEY to a base64-encoded 32-byte key before creating accounts.'
            );
        }

        $this->encryptionKey = $key;
        $frontendUrl = rtrim(
            getenv('AROWIN_PUBLIC_FRONTEND_URL') ?: 'http://localhost:5173',
            '/'
        );
        $urlParts = parse_url($frontendUrl);
        $localHosts = ['localhost', '127.0.0.1', '::1'];
        if (!is_array($urlParts)
            || !isset($urlParts['scheme'], $urlParts['host'])
            || !in_array(strtolower($urlParts['scheme']), ['http', 'https'], true)
            || (strtolower($urlParts['scheme']) !== 'https' && !in_array(strtolower($urlParts['host']), $localHosts, true))
            || isset($urlParts['user'])
            || isset($urlParts['pass'])) {
            throw new RuntimeException('AROWIN_PUBLIC_FRONTEND_URL must use HTTPS except for localhost development.');
        }
        $this->frontendUrl = $frontendUrl;
    }

    public function prepare(int $userId, string $password): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::LINK_LIFETIME_SECONDS);
        $statement = $this->database->prepare(
            'INSERT INTO credential_downloads
                (user_id, token_hash, encrypted_password, expires_at)
             VALUES (:user_id, :token_hash, :encrypted_password, :expires_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'encrypted_password' => $this->encrypt($password),
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $token,
            'url' => $this->downloadUrl($token),
            'expiresAt' => $expiresAt,
        ];
    }

    public function sendLink(string $email, string $name, string $role, string $url, string $expiresAt): void
    {
        $smtpPassword = getenv('SMTP_PASSWORD');
        $smtpPassword = is_string($smtpPassword) ? str_replace(' ', '', trim($smtpPassword)) : '';
        if ($smtpPassword === '') {
            throw new RuntimeException('SMTP_PASSWORD is not configured.');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $mail->Port = (int) (getenv('SMTP_PORT') ?: '587');
        $mail->SMTPAuth = true;
        $mail->Username = getenv('SMTP_USERNAME') ?: self::SENDER_EMAIL;
        $mail->Password = $smtpPassword;
        $encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');
        if ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            throw new RuntimeException('SMTP_ENCRYPTION must be either tls or ssl.');
        }
        $mail->Timeout = 20;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->setFrom(self::SENDER_EMAIL, 'AROWIN');
        $mail->addReplyTo(self::SENDER_EMAIL, 'AROWIN');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'AROWIN | Your employee account is ready';

        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeRole = htmlspecialchars($role, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLoginUrl = htmlspecialchars($this->frontendUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $installUrl = $this->frontendUrl . '/?install=1';
        $safeInstallUrl = htmlspecialchars($installUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeExpiry = htmlspecialchars($expiresAt . ' UTC', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $mail->Body = <<<HTML
<!doctype html>
<html lang="en">
<body style="margin:0;background:#f3f7fd;font-family:Arial,sans-serif;color:#14213a">
  <div style="max-width:560px;margin:32px auto;padding:32px;background:#fff;border:1px solid #e1e9f4;border-radius:16px">
    <div style="font-size:13px;font-weight:800;letter-spacing:3px;color:#0872ed">AROWIN</div>
    <h1 style="font-size:22px;margin:20px 0 10px">Your employee account is ready</h1>
    <p style="font-size:15px;line-height:1.6">Hello {$safeName}, your {$safeRole} account has been created.</p>
    <p style="font-size:14px;line-height:1.6">Use the secure link below to download your sign-in email and password. This link can be used once and expires at {$safeExpiry}.</p>
    <p style="margin:26px 0"><a href="{$safeUrl}" style="display:inline-block;padding:13px 20px;border-radius:9px;background:#0872ed;color:#fff;text-decoration:none;font-weight:700">Download login credentials</a></p>
    <p style="font-size:14px;line-height:1.6">Install the AROWIN web app on your device for quick access to your {$safeRole} dashboard:</p>
    <p style="margin:20px 0"><a href="{$safeInstallUrl}" style="display:inline-block;padding:12px 18px;border:1px solid #cfe0f5;border-radius:9px;color:#0872ed;text-decoration:none;font-weight:700">Install AROWIN Web App</a></p>
    <p style="font-size:13px;line-height:1.6;color:#64748b">Open the link on your device and choose “Install app” or “Add to Home Screen” in your browser. On iPhone or iPad, use Share, then “Add to Home Screen”. You can also sign in at <a href="{$safeLoginUrl}" style="color:#0872ed">{$safeLoginUrl}</a>.</p>
    <p style="font-size:12px;line-height:1.6;color:#64748b">For your security, your password is not included in this email. If you did not expect this account, contact your AROWIN administrator.</p>
  </div>
</body>
</html>
HTML;
        $mail->AltBody = "Hello {$name}, your {$role} account has been created.\n\n"
            . "Download your sign-in email and password using this one-time link (expires {$expiresAt} UTC):\n{$url}\n\n"
            . "Install the AROWIN web app for quick access to your dashboard: {$installUrl}\n"
            . "Open the link on your device and choose Install app or Add to Home Screen in your browser. On iPhone or iPad, use Share, then Add to Home Screen.\n"
            . "You can also sign in to AROWIN here: {$this->frontendUrl}\n\n"
            . 'For your security, the password is not included in this email.';

        try {
            $mail->send();
        } catch (MailException $error) {
            throw new RuntimeException('SMTP could not send the credentials email.', 0, $error);
        }
    }

    public function markEmailSent(string $token): void
    {
        $now = $this->utcNowExpression();
        $statement = $this->database->prepare(
            "UPDATE credential_downloads
             SET email_sent_at = {$now}
             WHERE token_hash = :token_hash"
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
    }

    public function resend(int $userId): array
    {
        $lock = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                'SELECT cd.id, cd.encrypted_password, u.email, u.name, u.role
                 FROM credential_downloads cd
                 INNER JOIN users u ON u.id = cd.user_id
                 WHERE cd.user_id = :user_id
                   AND cd.redeemed_at IS NULL
                   AND cd.encrypted_password IS NOT NULL
                   AND u.is_active = 1
                 ORDER BY cd.id DESC
                 LIMIT 1' . $lock
            );
            $statement->execute(['user_id' => $userId]);
            $record = $statement->fetch();
            if (!is_array($record)) {
                throw new \DomainException('No unused credentials are available to resend for this account.');
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = gmdate('Y-m-d H:i:s', time() + self::LINK_LIFETIME_SECONDS);
            $update = $this->database->prepare(
                'UPDATE credential_downloads
                 SET token_hash = :token_hash, expires_at = :expires_at, email_sent_at = NULL
                 WHERE id = :id'
            );
            $update->execute([
                'token_hash' => hash('sha256', $token),
                'expires_at' => $expiresAt,
                'id' => $record['id'],
            ]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $error;
        }

        return [
            'token' => $token,
            'url' => $this->downloadUrl($token),
            'expiresAt' => $expiresAt,
            'email' => $record['email'],
            'name' => $record['name'],
            'role' => $record['role'],
        ];
    }

    public function inspect(string $token): array
    {
        $now = $this->utcNowExpression();
        $statement = $this->database->prepare(
            "SELECT u.employee_id, u.name, u.role, cd.expires_at
             FROM credential_downloads cd
             INNER JOIN users u ON u.id = cd.user_id
             WHERE cd.token_hash = :token_hash
               AND cd.redeemed_at IS NULL
               AND cd.encrypted_password IS NOT NULL
               AND cd.expires_at > {$now}
               AND u.is_active = 1
             LIMIT 1"
        );
        $statement->execute(['token_hash' => hash('sha256', $token)]);
        $record = $statement->fetch();
        if (!is_array($record)) {
            throw new \DomainException('This credentials link is invalid, expired, or already used.');
        }

        return [
            'name' => $record['name'],
            'employeeId' => $record['employee_id'],
            'role' => $record['role'],
            'expiresAt' => $record['expires_at'],
        ];
    }

    public function redeem(string $token): array
    {
        $lock = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $now = $this->utcNowExpression();
        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                "SELECT cd.id, cd.encrypted_password, cd.expires_at, u.employee_id, u.name, u.email, u.role
                 FROM credential_downloads cd
                 INNER JOIN users u ON u.id = cd.user_id
                 WHERE cd.token_hash = :token_hash
                   AND cd.redeemed_at IS NULL
                   AND cd.encrypted_password IS NOT NULL
                   AND cd.expires_at > {$now}
                   AND u.is_active = 1
                 LIMIT 1" . $lock
            );
            $statement->execute(['token_hash' => hash('sha256', $token)]);
            $record = $statement->fetch();
            if (!is_array($record)) {
                throw new \DomainException('This credentials link is invalid, expired, or already used.');
            }

            $password = $this->decrypt($record['encrypted_password']);
            $update = $this->database->prepare(
                "UPDATE credential_downloads
                 SET redeemed_at = {$now}, encrypted_password = NULL
                 WHERE id = :id AND redeemed_at IS NULL"
            );
            $update->execute(['id' => $record['id']]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('This credentials link has already been used.');
            }
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $error;
        }

        return [
            'name' => $record['name'],
            'employeeId' => $record['employee_id'],
            'email' => $record['email'],
            'role' => $record['role'],
            'password' => $password,
        ];
    }

    private function encrypt(string $value): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $this->encryptionKey, OPENSSL_RAW_DATA, $nonce, $tag);
        if (!is_string($ciphertext)) {
            throw new RuntimeException('Could not protect the employee password for secure delivery.');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    private function decrypt(string $value): string
    {
        $payload = base64_decode($value, true);
        if (!is_string($payload) || strlen($payload) < 29) {
            throw new RuntimeException('Stored credentials could not be decrypted.');
        }

        $password = openssl_decrypt(
            substr($payload, 28),
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16)
        );
        if (!is_string($password)) {
            throw new RuntimeException('Stored credentials could not be decrypted. Check AROWIN_CREDENTIAL_ENCRYPTION_KEY.');
        }

        return $password;
    }

    private function downloadUrl(string $token): string
    {
        return $this->frontendUrl . '/credentials/' . $token;
    }

    private function utcNowExpression(): string
    {
        return $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'UTC_TIMESTAMP()'
            : 'CURRENT_TIMESTAMP';
    }
}
