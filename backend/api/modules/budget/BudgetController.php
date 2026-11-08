<?php

declare(strict_types=1);

namespace Arowin\Modules\Budget;

use Arowin\EmployeeRoles;
use Arowin\Shared\ValueFormatter;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class BudgetController
{
    private const BUDGET_CATEGORIES = [
        'office',
        'salaries',
        'assets',
        'expenses',
        'advances',
        'ads_promotions',
    ];

    public function __construct(private PDO $database)
    {
    }

    public function summary(): array
    {
        $companyBudget = $this->database->query(
            'SELECT total_budget, bank_name, account_holder_name, account_number, ifsc_code
             FROM company_budgets WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        $allocations = array_fill_keys(self::BUDGET_CATEGORIES, '0.00');
        $assignedCategories = array_fill_keys(self::BUDGET_CATEGORIES, false);
        $configuredCategories = 0;
        $statement = $this->database->query(
            'SELECT category_key, allocated_amount FROM budget_allocations'
        );
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['category_key'], $allocations)) {
                $allocations[$row['category_key']] = (string) $row['allocated_amount'];
                $assignedCategories[$row['category_key']] = true;
                $configuredCategories++;
            }
        }

        return [
            'companyBudget' => $companyBudget === false ? null : (string) $companyBudget['total_budget'],
            'bankDetails' => $companyBudget === false ? null : [
                'bankName' => (string) $companyBudget['bank_name'],
                'accountHolderName' => (string) $companyBudget['account_holder_name'],
                'maskedAccountNumber' => $this->maskAccountNumber((string) $companyBudget['account_number']),
                'hasAccountNumber' => (string) $companyBudget['account_number'] !== '',
                'ifscCode' => (string) $companyBudget['ifsc_code'],
            ],
            'allocations' => $allocations,
            'assignedCategories' => $assignedCategories,
            'allocationsConfigured' => $configuredCategories === count(self::BUDGET_CATEGORIES),
        ];
    }

    public function saveCompanyBudget(array $payload, int $configuredBy): array
    {
        $amount = $this->validBudgetAmount($payload['totalBudget'] ?? null, 'Enter a valid company budget greater than zero.');
        $bankName = $this->validRequiredText($payload['bankName'] ?? null, 120, 'Enter the bank name.');
        $accountHolderName = $this->validRequiredText(
            $payload['accountHolderName'] ?? null,
            120,
            'Enter the account holder name.'
        );
        $ifscCode = strtoupper(trim((string) ($payload['ifscCode'] ?? '')));
        if (preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifscCode) !== 1) {
            throw new \InvalidArgumentException('Enter a valid 11-character IFSC code.');
        }

        $accountNumber = trim((string) ($payload['accountNumber'] ?? ''));
        if ($accountNumber !== '' && preg_match('/^[0-9]{6,34}$/', $accountNumber) !== 1) {
            throw new \InvalidArgumentException('Enter a valid account number using 6 to 34 digits.');
        }

        $this->database->beginTransaction();
        try {
            $existingQuery = $this->database->query(
                'SELECT account_number FROM company_budgets WHERE id = 1 FOR UPDATE'
            );
            $existingAccountNumber = $existingQuery->fetchColumn();
            if ($accountNumber === '') {
                if ($existingAccountNumber === false || $existingAccountNumber === '') {
                    throw new \InvalidArgumentException('Enter the bank account number.');
                }
                $accountNumber = (string) $existingAccountNumber;
            }

            $statement = $this->database->prepare(
                'INSERT INTO company_budgets
                    (id, total_budget, bank_name, account_holder_name, account_number, ifsc_code, configured_by)
                 VALUES (1, :total_budget, :bank_name, :account_holder_name, :account_number, :ifsc_code, :configured_by)
                 ON DUPLICATE KEY UPDATE
                    total_budget = VALUES(total_budget),
                    bank_name = VALUES(bank_name),
                    account_holder_name = VALUES(account_holder_name),
                    account_number = VALUES(account_number),
                    ifsc_code = VALUES(ifsc_code),
                    configured_by = VALUES(configured_by)'
            );
            $statement->execute([
                'total_budget' => $amount,
                'bank_name' => $bankName,
                'account_holder_name' => $accountHolderName,
                'account_number' => $accountNumber,
                'ifsc_code' => $ifscCode,
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
            'companyBudget' => $amount,
            'bankDetails' => [
                'bankName' => $bankName,
                'accountHolderName' => $accountHolderName,
                'maskedAccountNumber' => $this->maskAccountNumber($accountNumber),
                'hasAccountNumber' => true,
                'ifscCode' => $ifscCode,
            ],
        ];
    }

    public function saveAllocations(array $payload, int $configuredBy): array
    {
        $amounts = $payload['allocations'] ?? null;
        if (!is_array($amounts) || count($amounts) !== 1) {
            throw new \InvalidArgumentException('Choose one category and enter its budget amount.');
        }

        $category = (string) array_key_first($amounts);
        if (!in_array($category, self::BUDGET_CATEGORIES, true)) {
            throw new \InvalidArgumentException('Choose a valid budget category.');
        }
        $amount = $this->validBudgetAmount($amounts[$category], 'Enter a valid category budget amount.', true);
        $amountCents = $this->amountToCents($amount);

        $this->database->beginTransaction();
        try {
            $companyQuery = $this->database->query(
                'SELECT total_budget FROM company_budgets WHERE id = 1 FOR UPDATE'
            );
            $companyBudget = $companyQuery->fetchColumn();
            if ($companyBudget === false) {
                throw new \DomainException('Add the Company Budget before assigning category budgets.');
            }

            $allocationQuery = $this->database->query(
                'SELECT category_key, allocated_amount FROM budget_allocations FOR UPDATE'
            );
            $otherAllocationsCents = 0;
            foreach ($allocationQuery->fetchAll(PDO::FETCH_ASSOC) as $allocation) {
                if (
                    $allocation['category_key'] !== $category
                    && in_array($allocation['category_key'], self::BUDGET_CATEGORIES, true)
                ) {
                    $otherAllocationsCents += $this->amountToCents((string) $allocation['allocated_amount']);
                }
            }
            if (
                $amountCents > 0
                && $otherAllocationsCents + $amountCents > $this->amountToCents((string) $companyBudget)
            ) {
                throw new \DomainException('Category budgets cannot exceed the remaining Company Budget.');
            }

            if ($amountCents === 0) {
                $delete = $this->database->prepare(
                    'DELETE FROM budget_allocations WHERE category_key = :category_key'
                );
                $delete->execute(['category_key' => $category]);
            } else {
                $upsert = $this->database->prepare(
                    'INSERT INTO budget_allocations (category_key, allocated_amount, configured_by)
                     VALUES (:category_key, :allocated_amount, :configured_by)
                     ON DUPLICATE KEY UPDATE
                        allocated_amount = VALUES(allocated_amount),
                        configured_by = VALUES(configured_by)'
                );
                $upsert->execute([
                    'category_key' => $category,
                    'allocated_amount' => $amount,
                    'configured_by' => $configuredBy,
                ]);
            }
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return ['allocations' => [$category => $amount]];
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
            'salaryAmount' => ValueFormatter::formatAmount($salaryAmount),
        ];
    }

    private function validBudgetAmount(mixed $value, string $message, bool $allowZero = false): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException($message);
        }
        $amount = trim((string) $value);
        if (!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/', $amount)) {
            throw new \InvalidArgumentException($message);
        }

        [$rupees, $paise] = array_pad(explode('.', $amount, 2), 2, '');
        $paise = str_pad($paise, 2, '0');
        if (!$allowZero && (int) $rupees === 0 && (int) $paise === 0) {
            throw new \InvalidArgumentException($message);
        }

        return $rupees . '.' . $paise;
    }

    private function amountToCents(string $amount): int
    {
        [$rupees, $paise] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $rupees * 100) + (int) str_pad($paise, 2, '0');
    }

    private function validRequiredText(mixed $value, int $maxLength, string $message): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException($message);
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException($message);
        }

        return $value;
    }

    private function maskAccountNumber(string $accountNumber): string
    {
        return str_repeat('•', max(0, strlen($accountNumber) - 4)) . substr($accountNumber, -4);
    }
}
