<?php

declare(strict_types=1);

use Arowin\Modules\Credentials\PhpMailerAdapter;
use PHPUnit\Framework\TestCase;
use PHPMailer\PHPMailer\PHPMailer;

final class PhpMailerAdapterTest extends TestCase
{
    public function testEmailShowsCredentialsAndLinksToInstallWebApp(): void
    {
        $previousPassword = getenv('SMTP_PASSWORD');
        putenv('SMTP_PASSWORD=test-smtp-password');
        $mail = null;

        try {
            $adapter = new PhpMailerAdapter(static function () use (&$mail): PHPMailer {
                $mail = new class(true) extends PHPMailer {
                    public function send(): bool
                    {
                        return true;
                    }
                };
                return $mail;
            });

            $adapter->sendEmployeeCredentials(
                'employee@example.com',
                'Employee <Name>',
                'Team Leader',
                '100123',
                'P&ssword<123>',
                'https://app.example.test',
                'https://app.example.test/?install=1'
            );
        } finally {
            if ($previousPassword === false) {
                putenv('SMTP_PASSWORD');
            } else {
                putenv('SMTP_PASSWORD=' . $previousPassword);
            }
        }

        $this->assertInstanceOf(PHPMailer::class, $mail);
        $this->assertStringContainsString('Employee ID:</strong> 100123', $mail->Body);
        $this->assertStringContainsString('employee@example.com', $mail->Body);
        $this->assertStringContainsString('P&amp;ssword&lt;123&gt;', $mail->Body);
        $this->assertStringContainsString('Install AROWIN Web App', $mail->Body);
        $this->assertStringContainsString('https://app.example.test/?install=1', $mail->Body);
        $this->assertStringNotContainsString('Download login credentials', $mail->Body);
        $this->assertStringContainsString('Password: P&ssword<123>', $mail->AltBody);
    }
}
