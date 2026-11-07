<?php

declare(strict_types=1);

namespace Arowin\Modules\Budget;

use Arowin\EmployeeRoles;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class BudgetController
{
    public function __construct(private PDO $database)
    {
    }

    public function listSalaries(): array
    {
        $statement = $this->database->query(
            "SELECT u.id, u.employee_id, u.name, u.email, u.role,
                    s.joining_date,
                    s.salary_amount
             FROM users u
             LEFT JOIN employee_salaries s ON s.user_id = u.id
             WHERE u.role <> 'sub_editor'
             ORDER BY u.name ASC, u.id ASC"
        );

        return array_map(static function (array $row): array {
            $salaryAmount = $row['salary_amount'];

            return [
                'id' => (int) $row['id'],
                'employeeId' => (string) $row['employee_id'],
                'fullName' => (string) $row['name'],
                'email' => (string) $row['email'],
                'role' => EmployeeRoles::label((string) $row['role']),
                'joiningDate' => $row['joining_date'],
                'salaryAmount' => $salaryAmount === null ? null : (string) $salaryAmount,
            ];
        }, $statement->fetchAll());
    }

    public function saveSalary(int $userId, array $payload, int $configuredBy): array
    {
        if ($userId < 1) {
            throw new \InvalidArgumentException('Choose a valid employee.');
        }

        $joiningDate = trim((string) ($payload['joiningDate'] ?? ''));
        $joining = DateTimeImmutable::createFromFormat('!Y-m-d', $joiningDate);
        if (!$joining || $joining->format('Y-m-d') !== $joiningDate) {
            throw new \InvalidArgumentException('Enter a valid joining date.');
        }

        $salaryAmount = trim((string) ($payload['salaryAmount'] ?? ''));
        if (!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/', $salaryAmount) || (float) $salaryAmount <= 0) {
            throw new \InvalidArgumentException('Enter a salary amount greater than zero with up to two decimal places.');
        }

        $employee = $this->database->prepare(
            "SELECT id FROM users WHERE id = :id AND role <> 'sub_editor' LIMIT 1"
        );
        $employee->execute(['id' => $userId]);
        if ($employee->fetchColumn() === false) {
            throw new \DomainException('The selected employee could not be found.');
        }

        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(
                'INSERT INTO employee_salaries (user_id, joining_date, salary_amount, configured_by)
                 VALUES (:user_id, :joining_date, :salary_amount, :configured_by)
                 ON DUPLICATE KEY UPDATE
                     joining_date = VALUES(joining_date),
                     salary_amount = VALUES(salary_amount),
                     configured_by = VALUES(configured_by)'
            );
            $statement->execute([
                'user_id' => $userId,
                'joining_date' => $joiningDate,
                'salary_amount' => $salaryAmount,
                'configured_by' => $configuredBy,
            ]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return [
            'userId' => $userId,
            'joiningDate' => $joiningDate,
            'salaryAmount' => number_format((float) $salaryAmount, 2, '.', ''),
        ];
    }
}
