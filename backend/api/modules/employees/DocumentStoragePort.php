<?php

declare(strict_types=1);

namespace Arowin\Modules\Employees;

interface DocumentStoragePort
{
    public function store(string $userId, array $file, string $type): array;

    public function delete(string $userId, string $relativePath): void;
}
