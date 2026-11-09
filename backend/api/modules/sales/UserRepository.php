<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

interface UserRepository
{
    public function findSalesEmployeeById(int $userId): ?array;
}
