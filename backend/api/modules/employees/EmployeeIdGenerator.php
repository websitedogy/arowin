<?php

declare(strict_types=1);

namespace Arowin\Modules\Employees;

use PDO;
use RuntimeException;

final class EmployeeIdGenerator
{
    public static function generate(PDO $database): string
    {
        $statement = $database->prepare(
            'SELECT 1 FROM users WHERE employee_id = :employee_id LIMIT 1'
        );

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $employeeId = (string) random_int(100000, 999999);
            $statement->execute(['employee_id' => $employeeId]);
            if ($statement->fetchColumn() === false) {
                return $employeeId;
            }
        }

        throw new RuntimeException('Could not generate an unused 6-digit Employee ID.');
    }
}
