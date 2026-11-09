<?php

declare(strict_types=1);

namespace Arowin\Modules\Credentials;

use PDO;

final class PdoCredentialRepository implements CredentialRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function insertRecord(int $userId, string $tokenHash, string $encryptedPassword, string $expiresAt): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO credential_downloads
                (user_id, token_hash, encrypted_password, expires_at)
             VALUES (:user_id, :token_hash, :encrypted_password, :expires_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'encrypted_password' => $encryptedPassword,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findUnusedByUserId(int $userId): ?array
    {
        $lock = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
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
        $record = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($record) ? $record : null;
    }

    public function findValidByTokenHash(string $tokenHash, string $now): ?array
    {
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
        $statement->execute(['token_hash' => $tokenHash]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($record) ? $record : null;
    }

    public function rotateLatestForUser(int $userId): ?array
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
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record)) {
                return null;
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = gmdate('Y-m-d H:i:s', time() + 86400);
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

            return [
                'token' => $token,
                'expiresAt' => $expiresAt,
                'email' => $record['email'],
                'name' => $record['name'],
                'role' => $record['role'],
            ];
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    public function markEmailSent(string $tokenHash, string $now): void
    {
        $statement = $this->database->prepare(
            "UPDATE credential_downloads
             SET email_sent_at = {$now}
             WHERE token_hash = :token_hash"
        );
        $statement->execute(['token_hash' => $tokenHash]);
    }

    public function redeemByTokenHash(string $tokenHash, string $now): ?array
    {
        $lock = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
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
            $statement->execute(['token_hash' => $tokenHash]);
            $record = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record)) {
                return null;
            }

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

            return [
                'id' => $record['id'],
                'encryptedPassword' => $record['encrypted_password'],
                'employeeId' => $record['employee_id'],
                'name' => $record['name'],
                'email' => $record['email'],
                'role' => $record['role'],
            ];
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }
}
