<?php

declare(strict_types=1);

namespace Arowin\Modules\Employees;

interface KycRepository
{
    public function findByUserId(int $userId): ?array;

    public function findExistingDocuments(int $userId): array;

    public function saveSubmission(int $userId, array $profile, array $bank, array $identity, array $documentRecords): array;
}
