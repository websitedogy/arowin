<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

use DomainException;
use PDO;
use RuntimeException;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

final class UnlockOtpController
{
    private const OTP_LIFETIME_SECONDS = 600;
    private const RESEND_WAIT_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly PDO $database)
    {
    }

    public function send(int $userId): void
    {
        $user = $this->salesEmployee($userId);
        $lastSentAt = (int) ($_SESSION['unlock_sales_otp_last_sent_at'] ?? 0);
        $waitSeconds = self::RESEND_WAIT_SECONDS - (time() - $lastSentAt);
        if ($waitSeconds > 0) {
            throw new DomainException(sprintf('Please wait %d seconds before requesting another code.', $waitSeconds));
        }

        $_SESSION['unlock_sales_otp_last_sent_at'] = time();
        unset($_SESSION['unlock_sales_otp'], $_SESSION['unlock_sales_otp_verified']);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->sendEmail($user['email'], $user['name'], $code);

        $_SESSION['unlock_sales_otp'] = [
            'userId' => $userId,
            'email' => $user['email'],
            'codeHash' => password_hash($code, PASSWORD_DEFAULT),
            'expiresAt' => time() + self::OTP_LIFETIME_SECONDS,
            'attempts' => 0,
        ];
    }

    public function verify(int $userId, array $payload): void
    {
        $user = $this->salesEmployee($userId);
        $code = trim((string) ($payload['otp'] ?? ''));
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            throw new \InvalidArgumentException('Enter the 6-digit email verification code.');
        }

        $challenge = $_SESSION['unlock_sales_otp'] ?? null;
        if (!is_array($challenge)
            || (int) ($challenge['userId'] ?? 0) !== $userId
            || !hash_equals((string) ($challenge['email'] ?? ''), $user['email'])
            || (int) ($challenge['expiresAt'] ?? 0) < time()) {
            unset($_SESSION['unlock_sales_otp']);
            throw new DomainException('The email verification code has expired. Request a new code.');
        }

        if ((int) $challenge['attempts'] >= self::MAX_ATTEMPTS) {
            unset($_SESSION['unlock_sales_otp']);
            throw new DomainException('Too many incorrect codes. Request a new code.');
        }

        if (!password_verify($code, (string) $challenge['codeHash'])) {
            $_SESSION['unlock_sales_otp']['attempts'] = (int) $challenge['attempts'] + 1;
            throw new DomainException('That code is incorrect. Check the email and try again.');
        }

        unset($_SESSION['unlock_sales_otp']);
        $_SESSION['unlock_sales_otp_verified'] = [
            'userId' => $userId,
            'email' => $user['email'],
            'expiresAt' => time() + 1800,
        ];
    }

    private function salesEmployee(int $userId): array
    {
        $statement = $this->database->prepare(
            "SELECT name, email FROM users
             WHERE id = :id AND role IN ('telecaller', 'team_leader') AND is_active = 1
             LIMIT 1"
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!is_array($user) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Your active employee email could not be verified. Contact your administrator.');
        }

        return [
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
        ];
    }

    private function sendEmail(string $email, string $name, string $code): void
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
        $mail->Username = getenv('SMTP_USERNAME') ?: 'arowintradin@gmail.com';
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
        $mail->setFrom('arowintradin@gmail.com', 'AROWIN');
        $mail->addReplyTo('arowintradin@gmail.com', 'AROWIN');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'AROWIN | Unlock sales email verification';
        $mail->Body = '<p>Your AROWIN unlock sales verification code is:</p>'
            . '<p style="font-size:24px;font-weight:bold;letter-spacing:5px">' . $code . '</p>'
            . '<p>This code expires in 10 minutes. If you did not request it, you can ignore this email.</p>';
        $mail->AltBody = "Your AROWIN unlock sales verification code is {$code}. "
            . 'It expires in 10 minutes. If you did not request it, you can ignore this email.';

        try {
            $mail->send();
        } catch (MailException $error) {
            throw new RuntimeException('SMTP could not send the unlock sales verification email.', 0, $error);
        }
    }
}
