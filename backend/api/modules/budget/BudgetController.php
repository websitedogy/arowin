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
    private const ACCOUNTING_TABLES = [
        'office' => [
            'entries' => 'office_accounting_entries',
            'proofs' => 'office_accounting_proofs',
        ],
        'assets' => [
            'entries' => 'assets_accounting_entries',
            'proofs' => 'assets_accounting_proofs',
        ],
    ];

    private const COST_TABLES = [
        'expenses' => [
            'entries' => 'expenses_entries',
            'proofs' => 'expenses_proofs',
        ],
        'ads_promotions' => [
            'entries' => 'ads_promotions_entries',
            'proofs' => 'ads_promotions_proofs',
        ],
    ];

    private const ADVANCE_PAYMENT_MODES = [
        'cash',
        'online',
        'cod',
        'net_banking',
        'upi',
        'card',
        'bank_transfer',
        'other',
    ];

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
                    s.salary_amount,
                    s.created_at
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
                'createdAt' => $row['created_at'] === null ? null : (string) $row['created_at'],
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

        $createdAtQuery = $this->database->prepare(
            'SELECT created_at FROM employee_salaries WHERE user_id = :user_id'
        );
        $createdAtQuery->execute(['user_id' => $userId]);
        $createdAt = $createdAtQuery->fetchColumn();
        if (!is_string($createdAt)) {
            throw new RuntimeException('The salary was saved but its creation time could not be loaded.');
        }

        return [
            'userId' => $userId,
            'joiningDate' => $joiningDate,
            'salaryAmount' => ValueFormatter::formatAmount($salaryAmount),
            'createdAt' => $createdAt,
        ];
    }

    public function listAdvances(): array
    {
        $statement = $this->database->query(
            'SELECT id, paid_to, reason, amount, payment_mode, is_returnable, has_expiry_date, expiry_date, created_at
             FROM budget_advances
             ORDER BY created_at DESC, id DESC'
        );

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'paidTo' => (string) $row['paid_to'],
                'reason' => (string) $row['reason'],
                'amount' => (string) $row['amount'],
                'paymentMode' => (string) $row['payment_mode'],
                'isReturnable' => (bool) $row['is_returnable'],
                'hasExpiryDate' => (bool) $row['has_expiry_date'],
                'expiryDate' => $row['expiry_date'] === null ? null : (string) $row['expiry_date'],
                'createdAt' => (string) $row['created_at'],
            ],
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function saveAdvance(array $payload, int $createdBy): array
    {
        $details = $this->advanceDetails($payload);
        $statement = $this->database->prepare(
            'INSERT INTO budget_advances
                (paid_to, reason, amount, payment_mode, is_returnable, has_expiry_date, expiry_date, created_by)
             VALUES
                (:paid_to, :reason, :amount, :payment_mode, :is_returnable, :has_expiry_date, :expiry_date, :created_by)'
        );
        $statement->execute([
            'paid_to' => $details['paidTo'],
            'reason' => $details['reason'],
            'amount' => $details['amount'],
            'payment_mode' => $details['paymentMode'],
            'is_returnable' => $details['isReturnable'] ? 1 : 0,
            'has_expiry_date' => $details['hasExpiryDate'] ? 1 : 0,
            'expiry_date' => $details['expiryDate'],
            'created_by' => $createdBy,
        ]);

        return $this->findAdvance((int) $this->database->lastInsertId());
    }

    public function updateAdvance(int $advanceId, array $payload): array
    {
        if ($advanceId < 1) {
            throw new \InvalidArgumentException('Choose a valid advance.');
        }
        $details = $this->advanceDetails($payload);
        $statement = $this->database->prepare(
            'UPDATE budget_advances
             SET paid_to = :paid_to,
                 reason = :reason,
                 amount = :amount,
                 payment_mode = :payment_mode,
                 is_returnable = :is_returnable,
                 has_expiry_date = :has_expiry_date,
                 expiry_date = :expiry_date
             WHERE id = :id'
        );
        $statement->execute([
            'paid_to' => $details['paidTo'],
            'reason' => $details['reason'],
            'amount' => $details['amount'],
            'payment_mode' => $details['paymentMode'],
            'is_returnable' => $details['isReturnable'] ? 1 : 0,
            'has_expiry_date' => $details['hasExpiryDate'] ? 1 : 0,
            'expiry_date' => $details['expiryDate'],
            'id' => $advanceId,
        ]);

        return $this->findAdvance($advanceId);
    }

    public function deleteAdvance(int $advanceId): void
    {
        if ($advanceId < 1) {
            throw new \InvalidArgumentException('Choose a valid advance.');
        }
        $statement = $this->database->prepare('DELETE FROM budget_advances WHERE id = :id');
        $statement->execute(['id' => $advanceId]);
        if ($statement->rowCount() === 0) {
            throw new \DomainException('Advance not found.');
        }
    }

    public function listCostEntries(string $category): array
    {
        $tables = $this->costTables($category);
        $entryTable = $tables['entries'];
        $statement = $this->database->query(
            "SELECT id, entry_name, amount, note, created_at
             FROM {$entryTable}
             ORDER BY created_at DESC, id DESC"
        );
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }

        $entries = [];
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $ids[] = $id;
            $entries[$id] = [
                'id' => $id,
                'entryName' => (string) $row['entry_name'],
                'amount' => (string) $row['amount'],
                'note' => (string) $row['note'],
                'createdAt' => (string) $row['created_at'],
                'proofs' => [],
            ];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $proofTable = $tables['proofs'];
        $proofQuery = $this->database->prepare(
            "SELECT id, entry_id, original_name, mime_type, file_size
             FROM {$proofTable}
             WHERE entry_id IN ({$placeholders})
             ORDER BY id ASC"
        );
        $proofQuery->execute($ids);
        foreach ($proofQuery->fetchAll(PDO::FETCH_ASSOC) as $proof) {
            $entries[(int) $proof['entry_id']]['proofs'][] = [
                'id' => (int) $proof['id'],
                'name' => (string) $proof['original_name'],
                'mimeType' => (string) $proof['mime_type'],
                'size' => (int) $proof['file_size'],
            ];
        }

        return array_values($entries);
    }

    public function saveCostEntry(string $category, array $payload, mixed $uploads, int $createdBy): array
    {
        $tables = $this->costTables($category);
        $details = $this->costDetails($payload);
        $proofs = $this->validateAccountingProofs($uploads);
        $storageDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'budget-accounting-proofs';
        if ($proofs !== []
            && !is_dir($storageDirectory)
            && !mkdir($storageDirectory, 0700, true)
            && !is_dir($storageDirectory)) {
            throw new RuntimeException('Proof storage could not be created.');
        }

        $savedPaths = [];
        $this->database->beginTransaction();
        try {
            $entryTable = $tables['entries'];
            $entryInsert = $this->database->prepare(
                "INSERT INTO {$entryTable} (entry_name, amount, note, created_by)
                 VALUES (:entry_name, :amount, :note, :created_by)"
            );
            $entryInsert->execute([
                'entry_name' => $details['entryName'],
                'amount' => $details['amount'],
                'note' => $details['note'],
                'created_by' => $createdBy,
            ]);
            $entryId = (int) $this->database->lastInsertId();
            $savedPaths = $this->storeAccountingProofs(
                $entryId,
                $proofs,
                $tables['proofs'],
                $storageDirectory
            );
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->removeAccountingProofFiles($savedPaths);
            throw $error;
        }

        return $this->findCostEntry($category, $entryId);
    }

    public function updateCostEntry(
        string $category,
        int $entryId,
        array $payload,
        mixed $uploads
    ): array {
        if ($entryId < 1) {
            throw new \InvalidArgumentException('Choose a valid entry.');
        }
        $tables = $this->costTables($category);
        $details = $this->costDetails($payload);
        $proofs = $this->validateAccountingProofs($uploads);
        $removeProofIds = $this->accountingProofIdsToRemove($payload['removeProofIds'] ?? []);
        $storageDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'budget-accounting-proofs';
        if ($proofs !== []
            && !is_dir($storageDirectory)
            && !mkdir($storageDirectory, 0700, true)
            && !is_dir($storageDirectory)) {
            throw new RuntimeException('Proof storage could not be created.');
        }

        $entryTable = $tables['entries'];
        $proofTable = $tables['proofs'];
        $savedPaths = [];
        $removedFileNames = [];
        $this->database->beginTransaction();
        try {
            $entryCheck = $this->database->prepare("SELECT id FROM {$entryTable} WHERE id = :id");
            $entryCheck->execute(['id' => $entryId]);
            if ($entryCheck->fetchColumn() === false) {
                throw new \DomainException('Entry not found.');
            }

            if ($removeProofIds !== []) {
                $placeholders = implode(',', array_fill(0, count($removeProofIds), '?'));
                $proofLookup = $this->database->prepare(
                    "SELECT id, file_name FROM {$proofTable}
                     WHERE entry_id = ? AND id IN ({$placeholders})"
                );
                $proofLookup->execute([$entryId, ...$removeProofIds]);
                $proofRows = $proofLookup->fetchAll(PDO::FETCH_ASSOC);
                if (count($proofRows) !== count($removeProofIds)) {
                    throw new \InvalidArgumentException('One or more selected proofs do not belong to this entry.');
                }
                $removedFileNames = array_map(
                    static fn (array $row): string => (string) $row['file_name'],
                    $proofRows
                );
            }

            $entryUpdate = $this->database->prepare(
                "UPDATE {$entryTable}
                 SET entry_name = :entry_name, amount = :amount, note = :note
                 WHERE id = :id"
            );
            $entryUpdate->execute([
                'entry_name' => $details['entryName'],
                'amount' => $details['amount'],
                'note' => $details['note'],
                'id' => $entryId,
            ]);

            if ($removeProofIds !== []) {
                $placeholders = implode(',', array_fill(0, count($removeProofIds), '?'));
                $proofDelete = $this->database->prepare(
                    "DELETE FROM {$proofTable} WHERE entry_id = ? AND id IN ({$placeholders})"
                );
                $proofDelete->execute([$entryId, ...$removeProofIds]);
            }

            $savedPaths = $this->storeAccountingProofs($entryId, $proofs, $proofTable, $storageDirectory);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->removeAccountingProofFiles($savedPaths);
            throw $error;
        }

        $this->removeAccountingProofFiles($this->accountingProofPaths($removedFileNames));

        return $this->findCostEntry($category, $entryId);
    }

    public function deleteCostEntry(string $category, int $entryId): void
    {
        if ($entryId < 1) {
            throw new \InvalidArgumentException('Choose a valid entry.');
        }
        $tables = $this->costTables($category);
        $entryTable = $tables['entries'];
        $proofTable = $tables['proofs'];
        $this->database->beginTransaction();
        try {
            $entryCheck = $this->database->prepare("SELECT id FROM {$entryTable} WHERE id = :id");
            $entryCheck->execute(['id' => $entryId]);
            if ($entryCheck->fetchColumn() === false) {
                throw new \DomainException('Entry not found.');
            }

            $proofQuery = $this->database->prepare(
                "SELECT file_name FROM {$proofTable} WHERE entry_id = :entry_id"
            );
            $proofQuery->execute(['entry_id' => $entryId]);
            $proofFileNames = array_map('strval', $proofQuery->fetchAll(PDO::FETCH_COLUMN));

            $delete = $this->database->prepare("DELETE FROM {$entryTable} WHERE id = :id");
            $delete->execute(['id' => $entryId]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        $this->removeAccountingProofFiles($this->accountingProofPaths($proofFileNames));
    }

    public function costProof(string $category, int $proofId): array
    {
        $proofTable = $this->costTables($category)['proofs'];
        $statement = $this->database->prepare(
            "SELECT file_name, original_name, mime_type FROM {$proofTable} WHERE id = :id"
        );
        $statement->execute(['id' => $proofId]);
        $proof = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($proof)) {
            throw new \DomainException('Proof not found.');
        }
        $fileName = (string) $proof['file_name'];
        if (preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/', $fileName) !== 1) {
            throw new RuntimeException('A proof has an invalid storage reference.');
        }
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'budget-accounting-proofs' . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('A proof could not be read from private storage.');
        }

        return [
            'path' => $path,
            'mimeType' => (string) $proof['mime_type'],
            'name' => (string) $proof['original_name'],
        ];
    }

    public function listAccountingEntries(string $category): array
    {
        $tables = $this->accountingTables($category);
        $entryTable = $tables['entries'];
        $statement = $this->database->prepare(
            "SELECT id, item_name, quantity, price_per_item, total_price, note, created_at
             FROM {$entryTable}
             ORDER BY created_at DESC, id DESC"
        );
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }

        $entries = [];
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $ids[] = $id;
            $entries[$id] = [
                'id' => $id,
                'itemName' => (string) $row['item_name'],
                'quantity' => (string) $row['quantity'],
                'pricePerItem' => (string) $row['price_per_item'],
                'totalPrice' => (string) $row['total_price'],
                'note' => (string) $row['note'],
                'createdAt' => (string) $row['created_at'],
                'proofs' => [],
            ];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $proofTable = $tables['proofs'];
        $proofQuery = $this->database->prepare(
            "SELECT id, entry_id, original_name, mime_type, file_size
             FROM {$proofTable}
             WHERE entry_id IN ({$placeholders})
             ORDER BY id ASC"
        );
        $proofQuery->execute($ids);
        foreach ($proofQuery->fetchAll(PDO::FETCH_ASSOC) as $proof) {
            $entries[(int) $proof['entry_id']]['proofs'][] = [
                'id' => (int) $proof['id'],
                'name' => (string) $proof['original_name'],
                'mimeType' => (string) $proof['mime_type'],
                'size' => (int) $proof['file_size'],
            ];
        }

        return array_values($entries);
    }

    public function saveAccountingEntry(string $category, array $payload, mixed $uploads, int $createdBy): array
    {
        $tables = $this->accountingTables($category);
        $details = $this->accountingDetails($payload);
        $proofs = $this->validateAccountingProofs($uploads);
        $storageDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'budget-accounting-proofs';
        if ($proofs !== []
            && !is_dir($storageDirectory)
            && !mkdir($storageDirectory, 0700, true)
            && !is_dir($storageDirectory)) {
            throw new RuntimeException('Accounting proof storage could not be created.');
        }

        $savedPaths = [];
        $this->database->beginTransaction();
        try {
            $entryTable = $tables['entries'];
            $entryInsert = $this->database->prepare(
                "INSERT INTO {$entryTable}
                    (item_name, quantity, price_per_item, total_price, note, created_by)
                 VALUES (:item_name, :quantity, :price_per_item, :total_price, :note, :created_by)"
            );
            $entryInsert->execute([
                'item_name' => $details['itemName'],
                'quantity' => $details['quantity'],
                'price_per_item' => $details['pricePerItem'],
                'total_price' => $details['totalPrice'],
                'note' => $details['note'],
                'created_by' => $createdBy,
            ]);
            $entryId = (int) $this->database->lastInsertId();

            $proofTable = $tables['proofs'];
            $savedPaths = $this->storeAccountingProofs($entryId, $proofs, $proofTable, $storageDirectory);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->removeAccountingProofFiles($savedPaths);
            throw $error;
        }

        return $this->findAccountingEntry($category, $entryId);
    }

    public function updateAccountingEntry(
        string $category,
        int $entryId,
        array $payload,
        mixed $uploads
    ): array {
        if ($entryId < 1) {
            throw new \InvalidArgumentException('Choose a valid accounting entry.');
        }
        $tables = $this->accountingTables($category);
        $details = $this->accountingDetails($payload);
        $proofs = $this->validateAccountingProofs($uploads);
        $removeProofIds = $this->accountingProofIdsToRemove($payload['removeProofIds'] ?? []);
        $storageDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'budget-accounting-proofs';
        if ($proofs !== []
            && !is_dir($storageDirectory)
            && !mkdir($storageDirectory, 0700, true)
            && !is_dir($storageDirectory)) {
            throw new RuntimeException('Accounting proof storage could not be created.');
        }

        $entryTable = $tables['entries'];
        $proofTable = $tables['proofs'];
        $savedPaths = [];
        $removedFileNames = [];
        $this->database->beginTransaction();
        try {
            $entryCheck = $this->database->prepare(
                "SELECT id FROM {$entryTable} WHERE id = :id"
            );
            $entryCheck->execute(['id' => $entryId]);
            if ($entryCheck->fetchColumn() === false) {
                throw new \DomainException('Accounting entry not found.');
            }

            if ($removeProofIds !== []) {
                $placeholders = implode(',', array_fill(0, count($removeProofIds), '?'));
                $proofLookup = $this->database->prepare(
                    "SELECT id, file_name FROM {$proofTable}
                     WHERE entry_id = ? AND id IN ({$placeholders})"
                );
                $proofLookup->execute([$entryId, ...$removeProofIds]);
                $proofRows = $proofLookup->fetchAll(PDO::FETCH_ASSOC);
                if (count($proofRows) !== count($removeProofIds)) {
                    throw new \InvalidArgumentException('One or more selected proofs do not belong to this entry.');
                }
                $removedFileNames = array_map(
                    static fn (array $row): string => (string) $row['file_name'],
                    $proofRows
                );
            }

            $entryUpdate = $this->database->prepare(
                "UPDATE {$entryTable}
                 SET item_name = :item_name,
                     quantity = :quantity,
                     price_per_item = :price_per_item,
                     total_price = :total_price,
                     note = :note
                 WHERE id = :id"
            );
            $entryUpdate->execute([
                'item_name' => $details['itemName'],
                'quantity' => $details['quantity'],
                'price_per_item' => $details['pricePerItem'],
                'total_price' => $details['totalPrice'],
                'note' => $details['note'],
                'id' => $entryId,
            ]);

            if ($removeProofIds !== []) {
                $placeholders = implode(',', array_fill(0, count($removeProofIds), '?'));
                $proofDelete = $this->database->prepare(
                    "DELETE FROM {$proofTable} WHERE entry_id = ? AND id IN ({$placeholders})"
                );
                $proofDelete->execute([$entryId, ...$removeProofIds]);
            }

            $savedPaths = $this->storeAccountingProofs($entryId, $proofs, $proofTable, $storageDirectory);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->removeAccountingProofFiles($savedPaths);
            throw $error;
        }

        $this->removeAccountingProofFiles($this->accountingProofPaths($removedFileNames));

        return $this->findAccountingEntry($category, $entryId);
    }

    public function deleteAccountingEntry(string $category, int $entryId): void
    {
        if ($entryId < 1) {
            throw new \InvalidArgumentException('Choose a valid accounting entry.');
        }
        $tables = $this->accountingTables($category);
        $entryTable = $tables['entries'];
        $proofTable = $tables['proofs'];
        $this->database->beginTransaction();
        try {
            $entryCheck = $this->database->prepare(
                "SELECT id FROM {$entryTable} WHERE id = :id"
            );
            $entryCheck->execute(['id' => $entryId]);
            if ($entryCheck->fetchColumn() === false) {
                throw new \DomainException('Accounting entry not found.');
            }

            $proofQuery = $this->database->prepare(
                "SELECT file_name FROM {$proofTable} WHERE entry_id = :entry_id"
            );
            $proofQuery->execute(['entry_id' => $entryId]);
            $proofFileNames = array_map('strval', $proofQuery->fetchAll(PDO::FETCH_COLUMN));

            $delete = $this->database->prepare("DELETE FROM {$entryTable} WHERE id = :id");
            $delete->execute(['id' => $entryId]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        $this->removeAccountingProofFiles($this->accountingProofPaths($proofFileNames));
    }

    public function accountingProof(string $category, int $proofId): array
    {
        $proofTable = $this->accountingTables($category)['proofs'];
        $statement = $this->database->prepare(
            "SELECT file_name, original_name, mime_type
             FROM {$proofTable}
             WHERE id = :id"
        );
        $statement->execute(['id' => $proofId]);
        $proof = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($proof)) {
            throw new \DomainException('Proof not found.');
        }
        $fileName = (string) $proof['file_name'];
        if (preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/', $fileName) !== 1) {
            throw new RuntimeException('An accounting proof has an invalid storage reference.');
        }
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'budget-accounting-proofs' . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('An accounting proof could not be read from private storage.');
        }

        return [
            'path' => $path,
            'mimeType' => (string) $proof['mime_type'],
            'name' => (string) $proof['original_name'],
        ];
    }

    private function accountingTables(string $category): array
    {
        if (!isset(self::ACCOUNTING_TABLES[$category])) {
            throw new \InvalidArgumentException('Choose Office or Assets accounting.');
        }
        return self::ACCOUNTING_TABLES[$category];
    }

    private function costTables(string $category): array
    {
        if (!isset(self::COST_TABLES[$category])) {
            throw new \InvalidArgumentException('Choose Expenses or Ads & Promotions.');
        }
        return self::COST_TABLES[$category];
    }

    private function advanceDetails(array $payload): array
    {
        $paidTo = $this->validRequiredText($payload['paidTo'] ?? null, 160, 'Enter who the advance is paid to.');
        $reason = $this->validRequiredText($payload['reason'] ?? null, 500, 'Enter the reason for this advance.');
        $amount = $this->validBudgetAmount($payload['amount'] ?? null, 'Enter a valid amount.');
        $paymentMode = $payload['paymentMode'] ?? null;
        if (!is_string($paymentMode) || !in_array($paymentMode, self::ADVANCE_PAYMENT_MODES, true)) {
            throw new \InvalidArgumentException('Choose a valid payment method.');
        }

        $returnableValue = $payload['isReturnable'] ?? null;
        if (!is_string($returnableValue) || !in_array($returnableValue, ['yes', 'no'], true)) {
            throw new \InvalidArgumentException('Choose whether this amount will be returned.');
        }
        $hasExpiryDateValue = $payload['hasExpiryDate'] ?? null;
        if (!is_string($hasExpiryDateValue) || !in_array($hasExpiryDateValue, ['yes', 'no'], true)) {
            throw new \InvalidArgumentException('Choose whether this advance has an expiry date.');
        }

        $hasExpiryDate = $hasExpiryDateValue === 'yes';
        $expiryDate = null;
        if ($hasExpiryDate) {
            $rawExpiryDate = $payload['expiryDate'] ?? null;
            if (!is_string($rawExpiryDate)) {
                throw new \InvalidArgumentException('Choose a valid expiry date.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawExpiryDate);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if ($date === false
                || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                || $date->format('Y-m-d') !== $rawExpiryDate) {
                throw new \InvalidArgumentException('Choose a valid expiry date.');
            }
            $expiryDate = $rawExpiryDate;
        }

        return [
            'paidTo' => $paidTo,
            'reason' => $reason,
            'amount' => $amount,
            'paymentMode' => $paymentMode,
            'isReturnable' => $returnableValue === 'yes',
            'hasExpiryDate' => $hasExpiryDate,
            'expiryDate' => $expiryDate,
        ];
    }

    private function findAdvance(int $advanceId): array
    {
        foreach ($this->listAdvances() as $advance) {
            if ($advance['id'] === $advanceId) {
                return $advance;
            }
        }
        throw new \DomainException('Advance not found.');
    }

    private function costDetails(array $payload): array
    {
        $entryName = $this->validRequiredText(
            $payload['entryName'] ?? null,
            160,
            'Enter the expense or promotion name.'
        );
        $amount = $this->validBudgetAmount($payload['amount'] ?? null, 'Enter a valid amount.');
        $rawNote = $payload['note'] ?? '';
        if (!is_string($rawNote)) {
            throw new \InvalidArgumentException('The note must be 500 characters or fewer.');
        }
        $note = trim($rawNote);
        if (strlen($note) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $note) === 1) {
            throw new \InvalidArgumentException('The note must be 500 characters or fewer.');
        }

        return [
            'entryName' => $entryName,
            'amount' => $amount,
            'note' => $note,
        ];
    }

    private function findCostEntry(string $category, int $entryId): array
    {
        foreach ($this->listCostEntries($category) as $entry) {
            if ($entry['id'] === $entryId) {
                return $entry;
            }
        }
        throw new RuntimeException('The entry was saved but could not be loaded.');
    }

    private function accountingDetails(array $payload): array
    {
        $itemName = $this->validRequiredText($payload['itemName'] ?? null, 160, 'Enter the item name.');
        $rawQuantity = $payload['quantity'] ?? null;
        if ((!is_string($rawQuantity) && !is_int($rawQuantity) && !is_float($rawQuantity))
            || preg_match('/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,3})?$/', trim((string) $rawQuantity)) !== 1) {
            throw new \InvalidArgumentException('Enter a valid quantity greater than zero.');
        }
        $quantity = trim((string) $rawQuantity);
        [$quantityWhole, $quantityFraction] = array_pad(explode('.', $quantity, 2), 2, '');
        $quantityMilli = ((int) $quantityWhole * 1000) + (int) str_pad($quantityFraction, 3, '0');
        if ($quantityMilli < 1) {
            throw new \InvalidArgumentException('Enter a valid quantity greater than zero.');
        }
        $quantity = $quantityWhole . '.' . str_pad($quantityFraction, 3, '0');

        $price = $this->validBudgetAmount($payload['pricePerItem'] ?? null, 'Enter a valid price per item.');
        $priceCents = $this->amountToCents($price);
        if ($priceCents < 1 || $priceCents > intdiv(PHP_INT_MAX - 500, $quantityMilli)) {
            throw new \InvalidArgumentException('The total price is outside the supported range.');
        }
        $totalCents = intdiv(($priceCents * $quantityMilli) + 500, 1000);
        if ($totalCents > 9999999999999999) {
            throw new \InvalidArgumentException('The total price is outside the supported range.');
        }
        $totalPrice = intdiv($totalCents, 100) . '.' . str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT);
        $rawNote = $payload['note'] ?? '';
        if (!is_string($rawNote)) {
            throw new \InvalidArgumentException('The note must be 500 characters or fewer.');
        }
        $note = trim($rawNote);
        if (strlen($note) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $note) === 1) {
            throw new \InvalidArgumentException('The note must be 500 characters or fewer.');
        }

        return [
            'itemName' => $itemName,
            'quantity' => $quantity,
            'pricePerItem' => $price,
            'totalPrice' => $totalPrice,
            'note' => $note,
        ];
    }

    private function accountingProofIdsToRemove(mixed $value): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Choose valid proofs to remove.');
        }
        $ids = [];
        foreach ($value as $id) {
            if ((!is_string($id) && !is_int($id)) || preg_match('/^[1-9][0-9]*$/', (string) $id) !== 1) {
                throw new \InvalidArgumentException('Choose valid proofs to remove.');
            }
            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    private function findAccountingEntry(string $category, int $entryId): array
    {
        foreach ($this->listAccountingEntries($category) as $entry) {
            if ($entry['id'] === $entryId) {
                return $entry;
            }
        }
        throw new RuntimeException('The accounting entry was saved but could not be loaded.');
    }

    private function storeAccountingProofs(
        int $entryId,
        array $proofs,
        string $proofTable,
        string $storageDirectory
    ): array {
        if ($proofs === []) {
            return [];
        }

        $savedPaths = [];
        try {
            $proofInsert = $this->database->prepare(
                "INSERT INTO {$proofTable} (entry_id, file_name, original_name, mime_type, file_size)
                 VALUES (:entry_id, :file_name, :original_name, :mime_type, :file_size)"
            );
            foreach ($proofs as $proof) {
                $extension = match ($proof['mimeType']) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                };
                $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
                $destination = $storageDirectory . DIRECTORY_SEPARATOR . $fileName;
                if (!move_uploaded_file($proof['tmpName'], $destination)) {
                    throw new RuntimeException('A proof image could not be saved. Please try uploading again.');
                }
                $savedPaths[] = $destination;
                if (!chmod($destination, 0600)) {
                    throw new RuntimeException('A proof image could not be secured in private storage.');
                }
                $proofInsert->execute([
                    'entry_id' => $entryId,
                    'file_name' => $fileName,
                    'original_name' => $proof['name'],
                    'mime_type' => $proof['mimeType'],
                    'file_size' => $proof['size'],
                ]);
            }
        } catch (\Throwable $error) {
            $this->removeAccountingProofFiles($savedPaths);
            throw $error;
        }

        return $savedPaths;
    }

    private function accountingProofPaths(array $fileNames): array
    {
        $directory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'budget-accounting-proofs';
        $paths = [];
        foreach ($fileNames as $fileName) {
            if (preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp)$/', $fileName) !== 1) {
                error_log('An accounting proof has an invalid storage reference.');
                continue;
            }
            $paths[] = $directory . DIRECTORY_SEPARATOR . $fileName;
        }
        return $paths;
    }

    private function removeAccountingProofFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path) && !unlink($path)) {
                error_log('Could not remove an accounting proof from private storage.');
            }
        }
    }

    private function validateAccountingProofs(mixed $uploads): array
    {
        if ($uploads === null) {
            return [];
        }
        if (!is_array($uploads) || !is_array($uploads['error'] ?? null)) {
            throw new \InvalidArgumentException('Choose valid proof images.');
        }
        $errors = $uploads['error'];
        $proofs = [];
        foreach (array_keys($errors) as $index) {
            $error = (int) $errors[$index];
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException(match ($error) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The server rejected a proof image upload. Try again or contact the administrator.',
                    UPLOAD_ERR_PARTIAL => 'A proof image upload was interrupted. Try again.',
                    default => 'A proof image could not be uploaded. Try again.',
                });
            }
            $temporaryPath = $uploads['tmp_name'][$index] ?? null;
            if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
                throw new \InvalidArgumentException('Choose a valid proof image.');
            }
            $size = (int) ($uploads['size'][$index] ?? 0);
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
            if (!is_string($mimeType) || !in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                throw new \InvalidArgumentException('Proofs must be JPEG, PNG, or WebP images.');
            }
            $imageInfo = getimagesize($temporaryPath);
            if (!is_array($imageInfo)
                || ($imageInfo['mime'] ?? '') !== $mimeType
                || (int) ($imageInfo[0] ?? 0) < 1
                || (int) ($imageInfo[1] ?? 0) < 1
                || (int) $imageInfo[0] * (int) $imageInfo[1] > 40_000_000) {
                throw new \InvalidArgumentException('One of the proof files is not a valid image.');
            }
            $originalName = preg_replace(
                '/[\x00-\x1F\x7F]/u',
                '',
                basename(str_replace('\\', '/', (string) ($uploads['name'][$index] ?? 'proof-image')))
            ) ?? '';
            $originalName = mb_substr(trim($originalName) ?: 'proof-image', 0, 180);
            $proofs[] = [
                'tmpName' => $temporaryPath,
                'name' => $originalName,
                'mimeType' => $mimeType,
                'size' => $size,
            ];
        }

        return $proofs;
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
