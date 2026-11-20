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

    public function sendEmployeeCredentials(
        string $email,
        string $name,
        string $role,
        string $employeeId,
        string $password,
        string $appUrl,
        string $installUrl
    ): void
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
        $safeEmployeeId = htmlspecialchars($employeeId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safePassword = htmlspecialchars($password, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeLoginUrl = htmlspecialchars($appUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeInstallUrl = htmlspecialchars($installUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $mail->Body = <<<HTML
<!doctype html>
<html lang="en">
<body style="margin:0;background:#f3f7fd;font-family:Arial,sans-serif;color:#14213a">
  <div style="max-width:560px;margin:32px auto;padding:32px;background:#fff;border:1px solid #e1e9f4;border-radius:16px">
    <div style="font-size:13px;font-weight:800;letter-spacing:3px;color:#0872ed">AROWIN</div>
    <h1 style="font-size:22px;margin:20px 0 10px">Your employee account is ready</h1>
    <p style="font-size:15px;line-height:1.6">Hello {$safeName}, your {$safeRole} account has been created.</p>
    <div style="margin:24px 0;padding:20px;background:#f7faff;border:1px solid #dce8f7;border-radius:12px">
      <p style="margin:0 0 14px;font-size:12px;font-weight:700;letter-spacing:1px;color:#64748b">YOUR LOGIN CREDENTIALS</p>
      <p style="margin:10px 0;font-size:14px"><strong>Employee ID:</strong> {$safeEmployeeId}</p>
      <p style="margin:10px 0;font-size:14px"><strong>Email / Username:</strong> {$safeEmail}</p>
      <p style="margin:10px 0;font-size:14px"><strong>Password:</strong> <span style="font-family:monospace;font-size:15px">{$safePassword}</span></p>
    </div>
    <p style="font-size:14px;line-height:1.6">Install the AROWIN web app on your device for quick access to your {$safeRole} dashboard:</p>
    <p style="margin:20px 0"><a href="{$safeInstallUrl}" style="display:inline-block;padding:12px 18px;border-radius:9px;background:#0872ed;color:#fff;text-decoration:none;font-weight:700">Install AROWIN Web App</a></p>
    <p style="font-size:13px;line-height:1.6;color:#64748b">Open the link on your device and choose “Install app” or “Add to Home Screen” in your browser. On iPhone or iPad, use Share, then “Add to Home Screen”. You can also sign in at <a href="{$safeLoginUrl}" style="color:#0872ed">{$safeLoginUrl}</a>.</p>
    <p style="font-size:12px;line-height:1.6;color:#64748b">Keep these credentials private. If you did not expect this account, contact your AROWIN administrator.</p>
  </div>
</body>
</html>
HTML;
        $mail->AltBody = "Hello {$name}, your {$role} account has been created.\n\n"
            . "YOUR LOGIN CREDENTIALS\n"
            . "Employee ID: {$employeeId}\n"
            . "Email / Username: {$email}\n"
            . "Password: {$password}\n\n"
            . "Install the AROWIN web app for quick access to your dashboard: {$installUrl}\n"
            . "Open the link on your device and choose Install app or Add to Home Screen in your browser. On iPhone or iPad, use Share, then Add to Home Screen.\n"
            . "You can also sign in to AROWIN here: {$appUrl}\n\n"
            . 'Keep these credentials private.';

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
