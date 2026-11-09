<?php

declare(strict_types=1);

namespace Arowin\Modules\Credentials;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class PhpMailerAdapter implements MailerPort
{
    private const SENDER_EMAIL = 'arowintradin@gmail.com';

    private readonly mixed $factory;

    public function __construct(?callable $factory = null)
    {
        $this->factory = $factory;
    }

    public function sendCredentialLink(string $email, string $name, string $role, string $url, string $expiresAt): void
    {
        $smtpPassword = getenv('SMTP_PASSWORD');
        $smtpPassword = is_string($smtpPassword) ? str_replace(' ', '', trim($smtpPassword)) : '';
        if ($smtpPassword === '') {
            throw new RuntimeException('SMTP_PASSWORD is not configured.');
        }

        $mail = $this->createMailer();
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
        $safeLoginUrl = htmlspecialchars(getenv('AROWIN_PUBLIC_FRONTEND_URL') ?: 'http://localhost:5173', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $installUrl = rtrim(getenv('AROWIN_PUBLIC_FRONTEND_URL') ?: 'http://localhost:5173', '/') . '/?install=1';
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
            . "You can also sign in to AROWIN here: " . (getenv('AROWIN_PUBLIC_FRONTEND_URL') ?: 'http://localhost:5173') . "\n\n"
            . 'For your security, the password is not included in this email.';

        $mail->send();
    }

    private function createMailer(): PHPMailer
    {
        if ($this->factory !== null) {
            $mailer = ($this->factory)();
            if (!$mailer instanceof PHPMailer) {
                throw new RuntimeException('The credential email factory must create a PHPMailer instance.');
            }
            return $mailer;
        }

        return new PHPMailer(true);
    }
}
