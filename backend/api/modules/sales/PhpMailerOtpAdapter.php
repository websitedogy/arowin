<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class PhpMailerOtpAdapter implements OtpMailerPort
{
    public function sendUnlockOtp(string $email, string $name, string $code): void
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
        $mail->send();
    }
}
