<?php

declare(strict_types=1);

namespace Arowin\Modules\Credentials;

use Arowin\EncryptionPort;
use Arowin\EnvironmentFrontendUrlProvider;
use Arowin\FrontendUrlProvider;
use PDO;
use RuntimeException;

final class CredentialDeliveryService
{
    private const LINK_LIFETIME_SECONDS = 86400;

    private readonly CredentialRepository $repository;
    private readonly MailerPort $mailer;
    private readonly EncryptionPort $encryption;
    private readonly FrontendUrlProvider $frontendUrlProvider;

    public function __construct(
        private readonly PDO $database,
        ?CredentialRepository $repository = null,
        ?MailerPort $mailer = null,
        ?EncryptionPort $encryption = null,
        ?FrontendUrlProvider $frontendUrlProvider = null
    ) {
        $this->repository = $repository ?? new PdoCredentialRepository($this->database);
        $this->mailer = $mailer ?? new PhpMailerAdapter();
        $this->encryption = $encryption ?? AesGcmEncryptionAdapter::fromBase64Env('AROWIN_CREDENTIAL_ENCRYPTION_KEY');
        $this->frontendUrlProvider = $frontendUrlProvider ?? new EnvironmentFrontendUrlProvider();
    }

    public function prepare(int $userId, string $password): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + self::LINK_LIFETIME_SECONDS);
        $this->repository->insertRecord(
            $userId,
            hash('sha256', $token),
            $this->encryption->encrypt($password),
            $expiresAt
        );

        return [
            'token' => $token,
            'url' => $this->downloadUrl($token),
            'expiresAt' => $expiresAt,
        ];
    }

    public function sendLink(string $email, string $name, string $role, string $url, string $expiresAt): void
    {
        $this->mailer->sendCredentialLink($email, $name, $role, $url, $expiresAt);
    }

    public function markEmailSent(string $token): void
    {
        $this->repository->markEmailSent(hash('sha256', $token), $this->utcNowExpression());
    }

    public function resend(int $userId): array
    {
        $record = $this->repository->rotateLatestForUser($userId);
        if ($record === null) {
            throw new \DomainException('No unused credentials are available to resend for this account.');
        }

        return [
            'token' => $record['token'],
            'url' => $this->downloadUrl($record['token']),
            'expiresAt' => $record['expiresAt'],
            'email' => $record['email'],
            'name' => $record['name'],
            'role' => $record['role'],
        ];
    }

    public function inspect(string $token): array
    {
        $record = $this->repository->findValidByTokenHash(hash('sha256', $token), $this->utcNowExpression());
        if ($record === null) {
            throw new \DomainException('This credentials link is invalid, expired, or already used.');
        }

        return [
            'name' => (string) $record['name'],
            'employeeId' => (string) $record['employee_id'],
            'role' => (string) $record['role'],
            'expiresAt' => (string) $record['expires_at'],
        ];
    }

    public function redeem(string $token): array
    {
        $record = $this->repository->redeemByTokenHash(hash('sha256', $token), $this->utcNowExpression());
        if ($record === null) {
            throw new \DomainException('This credentials link is invalid, expired, or already used.');
        }

        return [
            'name' => (string) $record['name'],
            'employeeId' => (string) $record['employeeId'],
            'email' => (string) $record['email'],
            'role' => (string) $record['role'],
            'password' => $this->encryption->decrypt((string) $record['encryptedPassword']),
        ];
    }

    private function downloadUrl(string $token): string
    {
        return $this->frontendUrlProvider->url() . '/credentials/' . $token;
    }

    private function utcNowExpression(): string
    {
        return $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'UTC_TIMESTAMP()'
            : 'CURRENT_TIMESTAMP';
    }
}
