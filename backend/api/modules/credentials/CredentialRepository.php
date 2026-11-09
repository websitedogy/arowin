<?php

declare(strict_types=1);

namespace Arowin\Modules\Credentials;

interface CredentialRepository
{
    public function insertRecord(int $userId, string $tokenHash, string $encryptedPassword, string $expiresAt): void;

    public function findUnusedByUserId(int $userId): ?array;

    public function findValidByTokenHash(string $tokenHash, string $now): ?array;

    public function rotateLatestForUser(int $userId): ?array;

    public function markEmailSent(string $tokenHash, string $now): void;

    public function redeemByTokenHash(string $tokenHash, string $now): ?array;
}
