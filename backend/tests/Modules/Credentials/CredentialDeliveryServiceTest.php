<?php

declare(strict_types=1);

use Arowin\EncryptionPort;
use Arowin\FrontendUrlProvider;
use Arowin\Modules\Credentials\CredentialDeliveryService;
use Arowin\Modules\Credentials\CredentialRepository;
use Arowin\Modules\Credentials\MailerPort;
use PHPUnit\Framework\TestCase;
use PDO;

final class CredentialDeliveryServiceTest extends TestCase
{
    public function testPrepareInspectAndRedeemUseInjectedDependencies(): void
    {
        $database = new PDO('sqlite::memory:');
        $repository = new class implements CredentialRepository {
            public array $records = [];

            public function insertRecord(int $userId, string $tokenHash, string $encryptedPassword, string $expiresAt): void
            {
                $this->records[] = [
                    'id' => count($this->records) + 1,
                    'userId' => $userId,
                    'tokenHash' => $tokenHash,
                    'encryptedPassword' => $encryptedPassword,
                    'expiresAt' => $expiresAt,
                    'redeemedAt' => null,
                    'emailSentAt' => null,
                    'name' => 'Alice Employee',
                    'email' => 'alice@example.com',
                    'role' => 'telecaller',
                ];
            }

            public function findUnusedByUserId(int $userId): ?array
            {
                foreach ($this->records as $record) {
                    if ((int) $record['userId'] === $userId && $record['redeemedAt'] === null) {
                        return $record;
                    }
                }
                return null;
            }

            public function findValidByTokenHash(string $tokenHash, string $now): ?array
            {
                $current = $now === 'CURRENT_TIMESTAMP' ? gmdate('Y-m-d H:i:s', time()) : $now;
                foreach ($this->records as $record) {
                    if ($record['tokenHash'] === $tokenHash
                        && $record['redeemedAt'] === null
                        && $record['encryptedPassword'] !== null
                        && $record['expiresAt'] > $current) {
                        return [
                            'employee_id' => 'EMP-102',
                            'name' => $record['name'],
                            'role' => $record['role'],
                            'expires_at' => $record['expiresAt'],
                        ];
                    }
                }
                return null;
            }

            public function rotateLatestForUser(int $userId): ?array
            {
                $record = null;
                foreach ($this->records as $row) {
                    if ((int) $row['userId'] === $userId) {
                        $record = $row;
                    }
                }
                if ($record === null) {
                    return null;
                }

                $token = bin2hex(random_bytes(16));
                $record['tokenHash'] = hash('sha256', $token);
                $record['expiresAt'] = gmdate('Y-m-d H:i:s', time() + 86400);
                $record['emailSentAt'] = null;
                return [
                    'token' => $token,
                    'expiresAt' => $record['expiresAt'],
                    'email' => $record['email'],
                    'name' => $record['name'],
                    'role' => $record['role'],
                ];
            }

            public function markEmailSent(string $tokenHash, string $now): void
            {
                foreach ($this->records as &$record) {
                    if ($record['tokenHash'] === $tokenHash) {
                        $record['emailSentAt'] = $now;
                    }
                }
                unset($record);
            }

            public function redeemByTokenHash(string $tokenHash, string $now): ?array
            {
                $current = $now === 'CURRENT_TIMESTAMP' ? gmdate('Y-m-d H:i:s', time()) : $now;
                foreach ($this->records as $index => $record) {
                    if ($record['tokenHash'] === $tokenHash && $record['redeemedAt'] === null && $record['expiresAt'] > $current) {
                        $this->records[$index]['redeemedAt'] = $current;
                        $this->records[$index]['encryptedPassword'] = null;
                        return [
                            'id' => $record['id'],
                            'encryptedPassword' => $record['encryptedPassword'],
                            'employeeId' => 'EMP-102',
                            'name' => $record['name'],
                            'email' => $record['email'],
                            'role' => $record['role'],
                        ];
                    }
                }
                return null;
            }
        };
        $mailer = new class implements MailerPort {
            public array $sent = [];

            public function sendCredentialLink(string $email, string $name, string $role, string $url, string $expiresAt): void
            {
                $this->sent[] = compact('email', 'name', 'role', 'url', 'expiresAt');
            }
        };
        $encryption = new class implements EncryptionPort {
            public function encrypt(string $value): string
            {
                return base64_encode($value);
            }

            public function decrypt(string $value): string
            {
                return base64_decode($value, true) ?: '';
            }
        };
        $frontend = new class implements FrontendUrlProvider {
            public function url(): string
            {
                return 'https://app.example.test';
            }
        };

        $service = new CredentialDeliveryService($database, $repository, $mailer, $encryption, $frontend);

        $prepared = $service->prepare(42, 'S3cret!Pass');
        $this->assertSame('https://app.example.test/credentials/' . $prepared['token'], $prepared['url']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', hash('sha256', $prepared['token']));
        $this->assertSame('S3cret!Pass', $encryption->decrypt($repository->records[0]['encryptedPassword']));

        $inspection = $service->inspect($prepared['token']);
        $this->assertSame('Alice Employee', $inspection['name']);
        $this->assertSame('EMP-102', $inspection['employeeId']);

        $service->markEmailSent($prepared['token']);
        $this->assertNotNull($repository->records[0]['emailSentAt']);

        $redeemed = $service->redeem($prepared['token']);
        $this->assertSame('Alice Employee', $redeemed['name']);
        $this->assertSame('S3cret!Pass', $redeemed['password']);

        $this->expectException(\DomainException::class);
        $service->inspect($prepared['token']);
    }

    public function testResendRotatesLatestCredentialLink(): void
    {
        $database = new PDO('sqlite::memory:');
        $repository = new class implements CredentialRepository {
            public array $records = [];

            public function insertRecord(int $userId, string $tokenHash, string $encryptedPassword, string $expiresAt): void
            {
                $this->records[] = [
                    'userId' => $userId,
                    'tokenHash' => $tokenHash,
                    'encryptedPassword' => $encryptedPassword,
                    'expiresAt' => $expiresAt,
                    'redeemedAt' => null,
                    'name' => 'Alice Employee',
                    'email' => 'alice@example.com',
                    'role' => 'telecaller',
                ];
            }

            public function findUnusedByUserId(int $userId): ?array
            {
                foreach ($this->records as $record) {
                    if ((int) $record['userId'] === $userId && $record['redeemedAt'] === null) {
                        return $record;
                    }
                }
                return null;
            }

            public function findValidByTokenHash(string $tokenHash, string $now): ?array
            {
                return null;
            }

            public function rotateLatestForUser(int $userId): ?array
            {
                foreach ($this->records as $record) {
                    if ((int) $record['userId'] === $userId) {
                        $token = bin2hex(random_bytes(16));
                        $record['tokenHash'] = hash('sha256', $token);
                        $record['expiresAt'] = gmdate('Y-m-d H:i:s', time() + 86400);
                        return [
                            'token' => $token,
                            'expiresAt' => $record['expiresAt'],
                            'email' => $record['email'],
                            'name' => $record['name'],
                            'role' => $record['role'],
                        ];
                    }
                }
                return null;
            }

            public function markEmailSent(string $tokenHash, string $now): void
            {
            }

            public function redeemByTokenHash(string $tokenHash, string $now): ?array
            {
                return null;
            }
        };
        $mailer = new class implements MailerPort {
            public function sendCredentialLink(string $email, string $name, string $role, string $url, string $expiresAt): void
            {
            }
        };
        $encryption = new class implements EncryptionPort {
            public function encrypt(string $value): string
            {
                return base64_encode($value);
            }

            public function decrypt(string $value): string
            {
                return base64_decode($value, true) ?: '';
            }
        };

        $repository->insertRecord(7, hash('sha256', 'old-token'), 'encrypted-password', gmdate('Y-m-d H:i:s', time() + 86400));
        $service = new CredentialDeliveryService($database, $repository, $mailer, $encryption, new class implements FrontendUrlProvider {
            public function url(): string
            {
                return 'https://app.example.test';
            }
        });

        $resend = $service->resend(7);
        $this->assertSame('https://app.example.test/credentials/' . $resend['token'], $resend['url']);
        $this->assertSame('Alice Employee', $resend['name']);
        $this->assertSame('alice@example.com', $resend['email']);
    }
}
