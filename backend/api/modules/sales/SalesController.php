<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

use Arowin\Shared\AppTime;
use Arowin\Shared\ValueFormatter;
use DateTimeImmutable;
use PDOException;
use PDO;

final class SalesController
{
    private const UNLOCK_PACKAGE_AMOUNTS = [
        '50.00',
        '150.00',
        '350.00',
        '750.00',
        '1550.00',
        '3150.00',
        '6350.00',
        '12750.00',
    ];

    public function __construct(private readonly PDO $database)
    {
    }

    public function performance(int $userId, string $role, string $month): array
    {
        $monthStart = ValueFormatter::normalizeMonth($month, 'Choose a valid sales month.');
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');

        if ($role === 'team_leader') {
            $membership = $this->database->prepare(
                'SELECT COUNT(*) FROM team_assignments WHERE team_leader_id = :user_id'
            );
            $membership->execute(['user_id' => $userId]);
            $memberCount = (int) $membership->fetchColumn();

            $target = $this->database->prepare(
                'SELECT target_amount
                 FROM team_leader_sales_targets
                 WHERE team_leader_id = :user_id AND target_month = :target_month
                 LIMIT 1'
            );
            $target->execute(['user_id' => $userId, 'target_month' => $monthStart]);
            $monthlyTarget = $target->fetchColumn();

            $sales = $this->database->prepare(
                'SELECT sales.sold_on, SUM(sales.package_amount) AS amount
                 FROM unlock_sales AS sales
                 INNER JOIN team_assignments AS assignment ON assignment.telecaller_id = sales.telecaller_id
                 WHERE assignment.team_leader_id = :user_id
                   AND sales.status = \'approved\'
                   AND sales.is_employee_credited = 1
                   AND sales.sold_on >= :month_start
                   AND sales.sold_on < :month_end
                 GROUP BY sales.sold_on
                 ORDER BY sales.sold_on ASC'
            );
            $sales->execute([
                'user_id' => $userId,
                'month_start' => $monthStart,
                'month_end' => $monthEnd,
            ]);

            return [
                'month' => substr($monthStart, 0, 7),
                'scope' => 'team',
                'memberCount' => $memberCount,
                'monthlyTarget' => $monthlyTarget === false ? null : (string) $monthlyTarget,
                'dailySales' => array_map(static fn (array $row): array => [
                    'date' => (string) $row['sold_on'],
                    'amount' => (string) $row['amount'],
                ], $sales->fetchAll()),
            ];
        }

        if (!in_array($role, ['team_leader', 'telecaller'], true)) {
            throw new \DomainException('Sales performance is only available to Team Leaders and Telecallers.');
        }

        $sales = $this->database->prepare(
            "SELECT sold_on, SUM(package_amount) AS amount
             FROM unlock_sales
             WHERE telecaller_id = :user_id AND status = 'approved' AND is_employee_credited = 1
               AND sold_on >= :month_start AND sold_on < :month_end
             GROUP BY sold_on
             ORDER BY sold_on ASC"
        );
        $sales->execute([
            'user_id' => $userId,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);

        $today = AppTime::now()->format('Y-m-d');
        $todayAmount = null;
        $dailySales = array_map(static function (array $row) use ($today, &$todayAmount): array {
            $amount = (string) $row['amount'];
            if ((string) $row['sold_on'] === $today) {
                $todayAmount = $amount;
            }

            return ['date' => (string) $row['sold_on'], 'amount' => $amount];
        }, $sales->fetchAll());

        return [
            'month' => substr($monthStart, 0, 7),
            'scope' => 'personal',
            'memberCount' => 1,
            'monthlyTarget' => null,
            'todayAmount' => $todayAmount,
            'dailySales' => $dailySales,
        ];
    }

    public function subEditorSummary(string $period): array
    {
        $today = AppTime::now();
        $endDate = $today->modify('+1 day')->format('Y-m-d');
        $startDate = null;
        $periodLabel = 'All recorded sales';

        if ($period === 'this_week') {
            $startDate = $today->modify('monday this week')->format('Y-m-d');
            $periodLabel = 'This week';
        } elseif ($period === 'last_month') {
            $startDate = $today->modify('first day of last month')->format('Y-m-d');
            $endDate = $today->modify('first day of this month')->format('Y-m-d');
            $periodLabel = 'Last month';
        } elseif ($period !== 'total') {
            throw new \InvalidArgumentException('Choose total, this_week, or last_month sales.');
        }

        $dateFilter = $startDate === null
            ? "WHERE sales.status = 'approved' AND sales.is_employee_credited = 1 AND sales.emergency_assignment_id IS NULL AND sales.sold_on < :end_date"
            : "WHERE sales.status = 'approved' AND sales.is_employee_credited = 1 AND sales.emergency_assignment_id IS NULL AND sales.sold_on >= :start_date AND sales.sold_on < :end_date";
        $statement = $this->database->prepare(
            "SELECT salesperson.id, salesperson.employee_id, salesperson.name, salesperson.role,
                    COUNT(sales.id) AS sales_entries,
                    SUM(sales.package_amount) AS total_sales,
                    MAX(sales.sold_on) AS last_sale_date
             FROM unlock_sales AS sales
             INNER JOIN users AS salesperson
               ON salesperson.id = sales.telecaller_id
              AND salesperson.role IN ('telecaller', 'team_leader')
             {$dateFilter}
             GROUP BY salesperson.id, salesperson.employee_id, salesperson.name, salesperson.role
             ORDER BY total_sales DESC, salesperson.name ASC, salesperson.id ASC"
        );
        $parameters = ['end_date' => $endDate];
        if ($startDate !== null) $parameters['start_date'] = $startDate;
        $statement->execute($parameters);

        $salespeople = array_map(static fn (array $row): array => [
            'employeeId' => (string) $row['employee_id'],
            'name' => (string) $row['name'],
            'role' => (string) $row['role'],
            'salesEntries' => (int) $row['sales_entries'],
            'totalSales' => (string) $row['total_sales'],
            'lastSaleDate' => (string) $row['last_sale_date'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
        $totalStatement = $this->database->prepare(
            "SELECT COALESCE(SUM(sales.package_amount), 0.00)
             FROM unlock_sales AS sales
             INNER JOIN users AS salesperson
               ON salesperson.id = sales.telecaller_id
              AND salesperson.role IN ('telecaller', 'team_leader')
             {$dateFilter}"
        );
        $totalStatement->execute($parameters);
        $dailySales = [];
        if ($period === 'this_week') {
            $dailyStatement = $this->database->prepare(
                "SELECT sales.sold_on, SUM(sales.package_amount) AS amount
                 FROM unlock_sales AS sales
                 INNER JOIN users AS salesperson
                   ON salesperson.id = sales.telecaller_id
                  AND salesperson.role IN ('telecaller', 'team_leader')
                 WHERE sales.status = 'approved'
                   AND sales.is_employee_credited = 1
                   AND sales.emergency_assignment_id IS NULL
                   AND sales.sold_on >= :start_date
                   AND sales.sold_on < :end_date
                 GROUP BY sales.sold_on
                 ORDER BY sales.sold_on ASC"
            );
            $dailyStatement->execute($parameters);
            $dailySales = array_map(static fn (array $row): array => [
                'date' => (string) $row['sold_on'],
                'amount' => (string) $row['amount'],
            ], $dailyStatement->fetchAll(PDO::FETCH_ASSOC));
        }

        return [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'startDate' => $startDate,
            'throughDate' => $startDate === null ? null : (new DateTimeImmutable($endDate))->modify('-1 day')->format('Y-m-d'),
            'totalSales' => (string) $totalStatement->fetchColumn(),
            'salespersonCount' => count($salespeople),
            'salesEntries' => array_sum(array_column($salespeople, 'salesEntries')),
            'salespeople' => $salespeople,
            'dailySales' => $dailySales,
        ];
    }

    public function saveDailySales(int $userId, string $role, array $payload): array
    {
        if ($role !== 'telecaller') {
            throw new \DomainException('Only Telecallers can record personal daily sales.');
        }

        $soldOn = trim((string) ($payload['date'] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $soldOn);
        $today = AppTime::now()->format('Y-m-d');
        if (!$date || $date->format('Y-m-d') !== $soldOn || $soldOn > $today) {
            throw new \InvalidArgumentException('Choose a valid date that is not in the future.');
        }

        $amount = trim((string) ($payload['amount'] ?? ''));
        if (!ValueFormatter::isValidSalesAmount($amount)) {
            throw new \InvalidArgumentException('Enter a valid USDT amount with up to two decimal places.');
        }

        $statement = $this->database->prepare(
            'INSERT INTO telecaller_daily_sales (telecaller_id, sold_on, amount)
             VALUES (:telecaller_id, :sold_on, :amount)
             ON DUPLICATE KEY UPDATE amount = VALUES(amount)'
        );
        $statement->execute([
            'telecaller_id' => $userId,
            'sold_on' => $soldOn,
            'amount' => $amount,
        ]);

        return [
            'date' => $soldOn,
            'amount' => ValueFormatter::formatAmount($amount),
        ];
    }

    public function listUnlockPackages(): array
    {
        $statement = $this->database->query(
            'SELECT package_amount, incentive_amount, updated_at
             FROM unlock_package_incentives
             ORDER BY package_amount ASC'
        );

        return array_map(static fn (array $row): array => [
            'packageAmount' => (string) $row['package_amount'],
            'incentiveAmount' => $row['incentive_amount'] === null ? null : (string) $row['incentive_amount'],
            'isConfigured' => $row['incentive_amount'] !== null,
            'updatedAt' => $row['updated_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listEmergencySalesAssignments(): array
    {
        $statement = $this->database->query(
            'SELECT assignment.id, assignment.package_amount, assignment.expires_at, assignment.note, assignment.created_at,
                    employee.employee_id, employee.name, employee.role
             FROM emergency_sales_assignments AS assignment
             INNER JOIN users AS employee ON employee.id = assignment.employee_id
             ORDER BY assignment.expires_at ASC, assignment.created_at DESC, assignment.id DESC'
        );
        $assignments = array_map(fn (array $row): array => $this->formatEmergencyAssignment($row), $statement->fetchAll(PDO::FETCH_ASSOC));
        $activeDaysLeft = array_column(
            array_values(array_filter($assignments, static fn (array $assignment): bool => $assignment['status'] === 'active')),
            'daysLeft'
        );

        return [
            'assignments' => $assignments,
            'nextExpiryDaysLeft' => $activeDaysLeft === [] ? null : min($activeDaysLeft),
        ];
    }

    public function listMyEmergencySalesAssignments(int $employeeId): array
    {
        $statement = $this->database->prepare(
            'SELECT assignment.id, assignment.package_amount, assignment.expires_at, assignment.note, assignment.created_at,
                    employee.employee_id, employee.name, employee.role
             FROM emergency_sales_assignments AS assignment
             INNER JOIN users AS employee ON employee.id = assignment.employee_id
             WHERE assignment.employee_id = :employee_id
             ORDER BY assignment.expires_at ASC, assignment.created_at DESC, assignment.id DESC'
        );
        $statement->execute(['employee_id' => $employeeId]);

        return array_map(fn (array $row): array => $this->formatEmergencyAssignment($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function myEmergencySalesOverview(int $employeeId): array
    {
        $salary = $this->database->prepare(
            'SELECT salary_amount FROM employee_salaries WHERE user_id = :employee_id LIMIT 1'
        );
        $salary->execute(['employee_id' => $employeeId]);
        $monthlySalary = $salary->fetchColumn();
        $progress = $this->emergencySalesProgress($employeeId);

        return [
            'monthlySalaryInr' => $monthlySalary === false || $monthlySalary === null
                ? null
                : (string) $monthlySalary,
            ...$progress,
        ];
    }

    public function emergencySalesProgress(int $employeeId): array
    {
        $assignmentQuery = $this->database->prepare(
            'SELECT id, package_amount, expires_at, created_at
             FROM emergency_sales_assignments
             WHERE employee_id = :employee_id
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );
        $assignmentQuery->execute(['employee_id' => $employeeId]);
        $assignment = $assignmentQuery->fetch(PDO::FETCH_ASSOC);

        if (!is_array($assignment)) {
            return [
                'emergencyAssignment' => null,
                'pendingSales' => ['count' => 0, 'amount' => '0.00'],
                'approvedSales' => ['count' => 0, 'amount' => '0.00'],
                'emergencySales' => [],
                'approvedEmergencySalesAmount' => '0.00',
                'remainingEmergencySalesAmount' => null,
                'unlockSalesUnlocked' => false,
                'canSubmitEmergencySale' => false,
            ];
        }

        $sales = $this->database->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN package_amount ELSE 0 END), 0.00) AS pending_amount,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_count,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN package_amount ELSE 0 END), 0.00) AS approved_amount
             FROM unlock_sales
             WHERE emergency_assignment_id = :assignment_id
               AND telecaller_id = :employee_id
               AND is_employee_credited = 1"
        );
        $sales->execute([
            'assignment_id' => $assignment['id'],
            'employee_id' => $employeeId,
        ]);
        $summary = $sales->fetch(PDO::FETCH_ASSOC);
        if (!is_array($summary)) {
            throw new \RuntimeException('Emergency sales progress could not be loaded.');
        }

        $entries = $this->database->prepare(
            'SELECT id, customer_reference, package_amount, status, rejection_reason, sold_on, created_at
             FROM unlock_sales
             WHERE emergency_assignment_id = :assignment_id AND telecaller_id = :employee_id
             ORDER BY created_at DESC, id DESC
             LIMIT 100'
        );
        $entries->execute([
            'assignment_id' => $assignment['id'],
            'employee_id' => $employeeId,
        ]);
        $targetAmount = (float) $assignment['package_amount'];
        $approvedAmount = (float) $summary['approved_amount'];
        $isComplete = $approvedAmount >= $targetAmount;
        $expiresAt = new DateTimeImmutable((string) $assignment['expires_at'], AppTime::timeZone());
        $today = AppTime::now()->setTime(0, 0);

        return [
            'emergencyAssignment' => [
                'id' => (int) $assignment['id'],
                'packageAmount' => (string) $assignment['package_amount'],
                'expiresAt' => $expiresAt->format('Y-m-d'),
                'assignedAt' => (string) $assignment['created_at'],
                'status' => $expiresAt >= $today ? 'active' : 'expired',
            ],
            'pendingSales' => [
                'count' => (int) $summary['pending_count'],
                'amount' => ValueFormatter::formatAmount((string) $summary['pending_amount']),
            ],
            'approvedSales' => [
                'count' => (int) $summary['approved_count'],
                'amount' => ValueFormatter::formatAmount((string) $summary['approved_amount']),
            ],
            'emergencySales' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'customerReference' => (string) $row['customer_reference'],
                'packageAmount' => (string) $row['package_amount'],
                'status' => (string) $row['status'],
                'rejectionReason' => $row['rejection_reason'] === null ? null : (string) $row['rejection_reason'],
                'soldOn' => (string) $row['sold_on'],
                'createdAt' => (string) $row['created_at'],
            ], $entries->fetchAll(PDO::FETCH_ASSOC)),
            'approvedEmergencySalesAmount' => ValueFormatter::formatAmount((string) $summary['approved_amount']),
            'remainingEmergencySalesAmount' => ValueFormatter::formatAmount(max(0, $targetAmount - $approvedAmount)),
            'unlockSalesUnlocked' => $isComplete,
            'canSubmitEmergencySale' => !$isComplete,
        ];
    }

    private function formatEmergencyAssignment(array $row): array
    {
        $today = AppTime::now()->setTime(0, 0);
        $expiresAt = new DateTimeImmutable((string) $row['expires_at'], AppTime::timeZone());
        $daysLeft = max(0, (int) $today->diff($expiresAt)->format('%r%a'));
        return [
            'id' => (int) $row['id'],
            'employeeId' => (string) $row['employee_id'],
            'employeeName' => (string) $row['name'],
            'employeeRole' => (string) $row['role'],
            'packageAmount' => (string) $row['package_amount'],
            'expiresAt' => $expiresAt->format('Y-m-d'),
            'note' => (string) $row['note'],
            'daysLeft' => $daysLeft,
            'status' => $expiresAt >= $today ? 'active' : 'expired',
            'assignedAt' => (string) $row['created_at'],
        ];
    }

    public function assignEmergencyPackage(array $payload, int $assignedBy): array
    {
        $employeeId = filter_var($payload['employeeId'] ?? null, FILTER_VALIDATE_INT);
        $rawPackageAmount = trim((string) ($payload['packageAmount'] ?? ''));
        $rawExpiryDate = trim((string) ($payload['expiryDate'] ?? ''));
        $note = trim((string) ($payload['note'] ?? ''));
        if ($employeeId === false || $employeeId < 1) {
            throw new \InvalidArgumentException('Choose an active Team Leader or Telecaller.');
        }
        if (!ValueFormatter::isValidSalesAmount($rawPackageAmount)
            || (float) $rawPackageAmount <= 0
            || strlen(explode('.', $rawPackageAmount, 2)[0]) > 8) {
            throw new \InvalidArgumentException('Enter an emergency sales amount greater than zero.');
        }
        $packageAmount = ValueFormatter::formatAmount($rawPackageAmount);
        $expiryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $rawExpiryDate, AppTime::timeZone());
        $dateErrors = DateTimeImmutable::getLastErrors();
        $today = AppTime::now()->setTime(0, 0);
        if ($expiryDate === false
            || $expiryDate->format('Y-m-d') !== $rawExpiryDate
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $expiryDate < $today
            || $expiryDate > $today->modify('+365 days')) {
            throw new \InvalidArgumentException('Choose an expiry date from today through the next 365 days.');
        }
        if (strlen($note) > 500) {
            throw new \InvalidArgumentException('The optional note must be 500 characters or fewer.');
        }

        $employee = $this->database->prepare(
            "SELECT id FROM users
             WHERE id = :employee_id AND role IN ('team_leader', 'telecaller') AND is_active = 1
             LIMIT 1"
        );
        $employee->execute(['employee_id' => $employeeId]);
        if ($employee->fetchColumn() === false) {
            throw new \InvalidArgumentException('Choose an active Team Leader or Telecaller.');
        }

        $activeAssignment = $this->database->prepare(
            'SELECT id FROM emergency_sales_assignments
             WHERE employee_id = :employee_id AND expires_at >= :today
             LIMIT 1'
        );
        $activeAssignment->execute([
            'employee_id' => $employeeId,
            'today' => $today->format('Y-m-d'),
        ]);
        if ($activeAssignment->fetchColumn() !== false) {
            throw new \DomainException('This employee already has an active emergency sales assignment.');
        }

        $expiresAt = $expiryDate->format('Y-m-d');
        $insert = $this->database->prepare(
            'INSERT INTO emergency_sales_assignments (employee_id, package_amount, expires_at, note, assigned_by)
             VALUES (:employee_id, :package_amount, :expires_at, :note, :assigned_by)'
        );
        $insert->execute([
            'employee_id' => $employeeId,
            'package_amount' => $packageAmount,
            'expires_at' => $expiresAt,
            'note' => $note,
            'assigned_by' => $assignedBy,
        ]);

        return [
            'id' => (int) $this->database->lastInsertId(),
            'employeeId' => $employeeId,
            'packageAmount' => $packageAmount,
            'expiresAt' => $expiresAt,
            'note' => $note,
            'daysLeft' => max(0, (int) $today->diff($expiryDate)->format('%r%a')),
            'status' => $expiryDate >= $today ? 'active' : 'expired',
        ];
    }

    public function configureUnlockPackages(array $payload, int $configuredBy): array
    {
        $incentives = $payload['incentives'] ?? null;
        if (!is_array($incentives) || count($incentives) !== count(self::UNLOCK_PACKAGE_AMOUNTS)) {
            throw new \InvalidArgumentException('Enter an incentive for each of the eight packages.');
        }

        $validated = [];
        foreach (self::UNLOCK_PACKAGE_AMOUNTS as $packageAmount) {
            $incentiveAmount = $incentives[$packageAmount] ?? null;
            if (
                (!is_string($incentiveAmount) && !is_int($incentiveAmount) && !is_float($incentiveAmount))
                || !ValueFormatter::isValidSalesAmount((string) $incentiveAmount)
            ) {
                throw new \InvalidArgumentException('Enter a valid non-negative incentive for every package.');
            }
            $validated[$packageAmount] = ValueFormatter::formatAmount((string) $incentiveAmount);
        }

        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(
                'UPDATE unlock_package_incentives
                 SET incentive_amount = :incentive_amount, configured_by = :configured_by
                 WHERE package_amount = :package_amount'
            );
            foreach ($validated as $packageAmount => $incentiveAmount) {
                $statement->execute([
                    'package_amount' => $packageAmount,
                    'incentive_amount' => $incentiveAmount,
                    'configured_by' => $configuredBy,
                ]);
                if ($statement->rowCount() === 0) {
                    $exists = $this->database->prepare(
                        'SELECT COUNT(*) FROM unlock_package_incentives WHERE package_amount = :package_amount'
                    );
                    $exists->execute(['package_amount' => $packageAmount]);
                    if ((int) $exists->fetchColumn() !== 1) {
                        throw new \RuntimeException('A supported unlock package is missing from the database. Run the database schema setup.');
                    }
                }
            }
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return $this->listUnlockPackages();
    }

    public function submitUnlockSale(int $employeeId, array $customer, ?int $emergencyAssignmentId = null): array
    {
        $customerId = trim((string) ($customer['customerId'] ?? ''));
        $packageAmount = (string) ($customer['packageAmount'] ?? '');
        $isEmployeeCredited = $customer['isEmployeeCredited'] ?? null;
        if (
            $customerId === ''
            || strlen($customerId) > 80
            || !in_array($packageAmount, self::UNLOCK_PACKAGE_AMOUNTS, true)
            || !is_bool($isEmployeeCredited)
        ) {
            throw new \InvalidArgumentException('Look up a customer with a valid Arowin Global package before submitting.');
        }

        $package = $this->database->prepare(
            'SELECT package_amount FROM unlock_package_incentives WHERE package_amount = :package_amount LIMIT 1'
        );
        $package->execute(['package_amount' => $packageAmount]);
        if ($package->fetchColumn() === false) {
            throw new \RuntimeException('The activated package is not configured for sales review.');
        }

        if ($emergencyAssignmentId !== null) {
            $progress = $this->emergencySalesProgress($employeeId);
            if (
                $progress['emergencyAssignment'] === null
                || $progress['emergencyAssignment']['id'] !== $emergencyAssignmentId
                || !$progress['canSubmitEmergencySale']
            ) {
                throw new \DomainException('There is no incomplete emergency sales assignment available.');
            }
            if (!$isEmployeeCredited) {
                throw new \InvalidArgumentException('Only employee-credited sales can complete an emergency sales assignment.');
            }
        }

        $soldOn = AppTime::now()->format('Y-m-d');
        if ($emergencyAssignmentId === null) {
            $insert = $this->database->prepare(
                "INSERT INTO unlock_sales
                    (telecaller_id, customer_reference, package_amount, is_employee_credited, status, sold_on)
                 VALUES (:employee_id, :customer_reference, :package_amount, :is_employee_credited, 'pending', :sold_on)"
            );
            $insert->execute([
                'employee_id' => $employeeId,
                'customer_reference' => $customerId,
                'package_amount' => $packageAmount,
                'is_employee_credited' => $isEmployeeCredited ? 1 : 0,
                'sold_on' => $soldOn,
            ]);
        } else {
            $insert = $this->database->prepare(
                "INSERT INTO unlock_sales
                    (telecaller_id, customer_reference, package_amount, is_employee_credited,
                     emergency_assignment_id, status, sold_on)
                 VALUES (:employee_id, :customer_reference, :package_amount, 1,
                         :emergency_assignment_id, 'pending', :sold_on)"
            );
            $insert->execute([
                'employee_id' => $employeeId,
                'customer_reference' => $customerId,
                'package_amount' => $packageAmount,
                'emergency_assignment_id' => $emergencyAssignmentId,
                'sold_on' => $soldOn,
            ]);
        }

        return [
            'id' => (int) $this->database->lastInsertId(),
            'customerReference' => $customerId,
            'packageAmount' => $packageAmount,
            'isEmployeeCredited' => $isEmployeeCredited,
            'status' => 'pending',
            'soldOn' => $soldOn,
            'emergencyAssignmentId' => $emergencyAssignmentId,
        ];
    }

    public function teamUnlockAchievements(int $employeeId, string $role): array
    {
        if (!in_array($role, ['team_leader', 'telecaller'], true)) {
            throw new \DomainException('Team achievements are only available to Team Leaders and Telecallers.');
        }

        $teamLeaderId = $employeeId;
        if ($role === 'telecaller') {
            $assignment = $this->database->prepare(
                'SELECT team_leader_id FROM team_assignments WHERE telecaller_id = :employee_id LIMIT 1'
            );
            $assignment->execute(['employee_id' => $employeeId]);
            $assignedLeaderId = $assignment->fetchColumn();
            if ($assignedLeaderId === false) {
                return ['achievements' => []];
            }
            $teamLeaderId = (int) $assignedLeaderId;
        }

        $statement = $this->database->prepare(
            "SELECT sale.id, employee.name
             FROM unlock_sales AS sale
             INNER JOIN team_assignments AS assignment
               ON assignment.telecaller_id = sale.telecaller_id
             INNER JOIN users AS employee
               ON employee.id = sale.telecaller_id AND employee.role = 'telecaller'
             WHERE assignment.team_leader_id = :team_leader_id
               AND sale.status = 'approved'
               AND sale.is_employee_credited = 1
               AND sale.emergency_assignment_id IS NULL
             ORDER BY sale.reviewed_at DESC, sale.id DESC
             LIMIT 500"
        );
        $statement->execute(['team_leader_id' => $teamLeaderId]);

        return [
            'achievements' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'employeeName' => (string) $row['name'],
            ], $statement->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    public function teamIncentives(int $teamLeaderId): array
    {
        $leader = $this->database->prepare(
            "SELECT id FROM users WHERE id = :id AND role = 'team_leader' LIMIT 1"
        );
        $leader->execute(['id' => $teamLeaderId]);
        if ($leader->fetchColumn() === false) {
            throw new \DomainException('Team incentives are only available to Team Leaders.');
        }

        $employees = $this->database->prepare(
            "SELECT employee.id, employee.employee_id, employee.name,
                    COUNT(sale.id) AS approved_sales,
                    COALESCE(SUM(sale.incentive_amount), 0.00) AS total_incentive
             FROM team_assignments AS assignment
             INNER JOIN users AS employee
               ON employee.id = assignment.telecaller_id AND employee.role = 'telecaller'
             LEFT JOIN unlock_sales AS sale
               ON sale.telecaller_id = employee.id AND sale.status = 'approved'
              AND sale.is_employee_credited = 1
              AND sale.emergency_assignment_id IS NULL
             WHERE assignment.team_leader_id = :team_leader_id
             GROUP BY employee.id, employee.employee_id, employee.name
             ORDER BY total_incentive DESC, employee.name ASC"
        );
        $employees->execute(['team_leader_id' => $teamLeaderId]);

        $totals = $this->database->prepare(
            "SELECT COALESCE(SUM(sale.incentive_amount), 0.00) AS total_incentive,
                    COUNT(sale.id) AS approved_sales
             FROM team_assignments AS assignment
             INNER JOIN users AS employee
               ON employee.id = assignment.telecaller_id AND employee.role = 'telecaller'
             LEFT JOIN unlock_sales AS sale
               ON sale.telecaller_id = employee.id AND sale.status = 'approved'
              AND sale.is_employee_credited = 1
              AND sale.emergency_assignment_id IS NULL
             WHERE assignment.team_leader_id = :team_leader_id"
        );
        $totals->execute(['team_leader_id' => $teamLeaderId]);
        $totalsRow = $totals->fetch(PDO::FETCH_ASSOC);

        $history = $this->database->prepare(
            "SELECT sale.id, employee.employee_id, employee.name AS employee_name,
                    sale.package_amount, sale.incentive_amount, sale.sold_on, sale.reviewed_at
             FROM team_assignments AS assignment
             INNER JOIN users AS employee
               ON employee.id = assignment.telecaller_id AND employee.role = 'telecaller'
             INNER JOIN unlock_sales AS sale
               ON sale.telecaller_id = employee.id AND sale.status = 'approved'
              AND sale.is_employee_credited = 1
              AND sale.emergency_assignment_id IS NULL
             WHERE assignment.team_leader_id = :team_leader_id
             ORDER BY sale.reviewed_at DESC, sale.id DESC
             LIMIT 500"
        );
        $history->execute(['team_leader_id' => $teamLeaderId]);

        $teamRows = $employees->fetchAll(PDO::FETCH_ASSOC);
        return [
            'totalIncentive' => (string) $totalsRow['total_incentive'],
            'approvedSales' => (int) $totalsRow['approved_sales'],
            'teamMemberCount' => count($teamRows),
            'employees' => array_map(static fn (array $row): array => [
                'employeeId' => (string) $row['employee_id'],
                'name' => (string) $row['name'],
                'approvedSales' => (int) $row['approved_sales'],
                'totalIncentive' => (string) $row['total_incentive'],
            ], $teamRows),
            'incentives' => array_map(static fn (array $row): array => [
                'saleId' => (int) $row['id'],
                'employeeId' => (string) $row['employee_id'],
                'employeeName' => (string) $row['employee_name'],
                'packageAmount' => (string) $row['package_amount'],
                'incentiveAmount' => (string) $row['incentive_amount'],
                'soldOn' => (string) $row['sold_on'],
                'approvedAt' => (string) $row['reviewed_at'],
            ], $history->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    public function teamTargetRewardSettings(): array
    {
        $amount = $this->database->query(
            'SELECT reward_amount FROM team_leader_target_reward_settings WHERE id = 1'
        )->fetchColumn();
        if ($amount === false) {
            throw new \RuntimeException('Team target reward settings are missing. Run the database schema setup.');
        }

        return ['rewardAmount' => (string) $amount];
    }

    public function saveTeamTargetRewardAmount(string $rawAmount, int $configuredBy): array
    {
        $amount = trim($rawAmount);
        if (!ValueFormatter::isValidSalesAmount($amount)) {
            throw new \InvalidArgumentException('Enter a valid non-negative Team Leader target reward in USDT.');
        }

        $this->database->beginTransaction();
        try {
            $upsert = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT INTO team_leader_target_reward_settings (id, reward_amount, configured_by)
                   VALUES (1, :reward_amount, :configured_by)
                   ON CONFLICT(id) DO UPDATE SET
                       reward_amount = excluded.reward_amount,
                       configured_by = excluded.configured_by,
                       updated_at = CURRENT_TIMESTAMP'
                : 'INSERT INTO team_leader_target_reward_settings (id, reward_amount, configured_by)
                   VALUES (1, :reward_amount, :configured_by)
                   ON DUPLICATE KEY UPDATE
                       reward_amount = VALUES(reward_amount),
                       configured_by = VALUES(configured_by),
                       updated_at = CURRENT_TIMESTAMP';
            $save = $this->database->prepare($upsert);
            $save->execute([
                'reward_amount' => ValueFormatter::formatAmount($amount),
                'configured_by' => $configuredBy,
            ]);

            $targets = $this->database->query(
                'SELECT team_leader_id, target_month FROM team_leader_sales_targets'
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($targets as $target) {
                $this->syncTeamTargetReward(
                    (int) $target['team_leader_id'],
                    substr((string) $target['target_month'], 0, 7)
                );
            }

            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return $this->teamTargetRewardSettings();
    }

    public function teamTargetRewards(int $teamLeaderId, string $month): array
    {
        $leader = $this->database->prepare(
            "SELECT id FROM users WHERE id = :id AND role = 'team_leader' LIMIT 1"
        );
        $leader->execute(['id' => $teamLeaderId]);
        if ($leader->fetchColumn() === false) {
            throw new \DomainException('Team rewards are only available to Team Leaders.');
        }

        $monthStart = ValueFormatter::normalizeMonth($month, 'Choose a valid target month.');
        $this->syncTeamTargetReward($teamLeaderId, substr($monthStart, 0, 7));
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');

        $targetQuery = $this->database->prepare(
            'SELECT target_amount FROM team_leader_sales_targets
             WHERE team_leader_id = :team_leader_id AND target_month = :target_month LIMIT 1'
        );
        $targetQuery->execute(['team_leader_id' => $teamLeaderId, 'target_month' => $monthStart]);
        $targetAmount = $targetQuery->fetchColumn();

        $salesQuery = $this->database->prepare(
            "SELECT COALESCE(SUM(sale.package_amount), 0.00)
             FROM team_assignments AS assignment
             INNER JOIN users AS employee
               ON employee.id = assignment.telecaller_id AND employee.role = 'telecaller'
             INNER JOIN unlock_sales AS sale
               ON sale.telecaller_id = employee.id AND sale.status = 'approved'
              AND sale.is_employee_credited = 1
              AND sale.emergency_assignment_id IS NULL
             WHERE assignment.team_leader_id = :team_leader_id
               AND sale.sold_on >= :month_start AND sale.sold_on < :month_end"
        );
        $salesQuery->execute([
            'team_leader_id' => $teamLeaderId,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);
        $teamSales = (float) $salesQuery->fetchColumn();

        $membersQuery = $this->database->prepare(
            "SELECT employee.employee_id, employee.name,
                    COALESCE(SUM(sale.package_amount), 0.00) AS sales_amount,
                    COUNT(sale.id) AS approved_sales
             FROM team_assignments AS assignment
             INNER JOIN users AS employee
               ON employee.id = assignment.telecaller_id AND employee.role = 'telecaller'
             LEFT JOIN unlock_sales AS sale
               ON sale.telecaller_id = employee.id
              AND sale.status = 'approved'
              AND sale.is_employee_credited = 1
              AND sale.emergency_assignment_id IS NULL
              AND sale.sold_on >= :month_start
              AND sale.sold_on < :month_end
             WHERE assignment.team_leader_id = :team_leader_id
             GROUP BY employee.id, employee.employee_id, employee.name
             ORDER BY sales_amount DESC, employee.name ASC"
        );
        $membersQuery->execute([
            'team_leader_id' => $teamLeaderId,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);
        $targetValue = $targetAmount === false ? null : (float) $targetAmount;
        $members = array_map(static function (array $row) use ($targetValue): array {
            $salesAmount = (float) $row['sales_amount'];

            return [
                'employeeId' => (string) $row['employee_id'],
                'name' => (string) $row['name'],
                'approvedSales' => (int) $row['approved_sales'],
                'salesAmount' => ValueFormatter::formatAmount($salesAmount),
                'targetContributionPercent' => $targetValue === null || $targetValue <= 0
                    ? 0
                    : round($salesAmount / $targetValue * 100, 2),
            ];
        }, $membersQuery->fetchAll(PDO::FETCH_ASSOC));

        $grantQuery = $this->database->prepare(
            'SELECT target_amount, achieved_sales_amount, reward_amount, granted_at
             FROM team_leader_target_rewards
             WHERE team_leader_id = :team_leader_id AND target_month = :target_month LIMIT 1'
        );
        $grantQuery->execute(['team_leader_id' => $teamLeaderId, 'target_month' => $monthStart]);
        $grant = $grantQuery->fetch(PDO::FETCH_ASSOC);

        $historyQuery = $this->database->prepare(
            'SELECT target_month, target_amount, achieved_sales_amount, reward_amount, granted_at
             FROM team_leader_target_rewards
             WHERE team_leader_id = :team_leader_id
             ORDER BY target_month DESC LIMIT 24'
        );
        $historyQuery->execute(['team_leader_id' => $teamLeaderId]);

        return [
            'month' => substr($monthStart, 0, 7),
            'targetAmount' => $targetValue === null ? null : ValueFormatter::formatAmount($targetValue),
            'teamSalesAmount' => ValueFormatter::formatAmount($teamSales),
            'remainingAmount' => $targetValue === null
                ? null
                : ValueFormatter::formatAmount(max(0, $targetValue - $teamSales)),
            'progressPercent' => $targetValue === null || $targetValue <= 0
                ? 0
                : round($teamSales / $targetValue * 100, 2),
            'targetAchieved' => $targetValue !== null && $teamSales >= $targetValue,
            'configuredRewardAmount' => $this->teamTargetRewardSettings()['rewardAmount'],
            'grant' => is_array($grant) ? [
                'targetAmount' => (string) $grant['target_amount'],
                'achievedSalesAmount' => (string) $grant['achieved_sales_amount'],
                'rewardAmount' => (string) $grant['reward_amount'],
                'grantedAt' => (string) $grant['granted_at'],
            ] : null,
            'members' => $members,
            'grantHistory' => array_map(static fn (array $row): array => [
                'month' => substr((string) $row['target_month'], 0, 7),
                'targetAmount' => (string) $row['target_amount'],
                'achievedSalesAmount' => (string) $row['achieved_sales_amount'],
                'rewardAmount' => (string) $row['reward_amount'],
                'grantedAt' => (string) $row['granted_at'],
            ], $historyQuery->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    public function syncTeamTargetReward(int $teamLeaderId, string $month): void
    {
        $monthStart = ValueFormatter::normalizeMonth($month, 'Choose a valid target month.');
        $targetQuery = $this->database->prepare(
            'SELECT target_amount FROM team_leader_sales_targets
             WHERE team_leader_id = :team_leader_id AND target_month = :target_month LIMIT 1'
        );
        $targetQuery->execute(['team_leader_id' => $teamLeaderId, 'target_month' => $monthStart]);
        $targetAmount = $targetQuery->fetchColumn();
        if ($targetAmount === false) {
            return;
        }

        $rewardAmount = (float) $this->teamTargetRewardSettings()['rewardAmount'];
        if ($rewardAmount <= 0) {
            return;
        }

        $monthEnd = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');
        $salesQuery = $this->database->prepare(
            "SELECT COALESCE(SUM(sale.package_amount), 0.00)
             FROM team_assignments AS assignment
             INNER JOIN users AS employee
               ON employee.id = assignment.telecaller_id AND employee.role = 'telecaller'
             INNER JOIN unlock_sales AS sale
               ON sale.telecaller_id = employee.id AND sale.status = 'approved'
              AND sale.is_employee_credited = 1
              AND sale.emergency_assignment_id IS NULL
             WHERE assignment.team_leader_id = :team_leader_id
               AND sale.sold_on >= :month_start AND sale.sold_on < :month_end"
        );
        $salesQuery->execute([
            'team_leader_id' => $teamLeaderId,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);
        $salesAmount = (float) $salesQuery->fetchColumn();
        if ($salesAmount < (float) $targetAmount) {
            return;
        }

        try {
            $grant = $this->database->prepare(
                'INSERT INTO team_leader_target_rewards
                    (team_leader_id, target_month, target_amount, achieved_sales_amount, reward_amount, granted_at)
                 VALUES
                    (:team_leader_id, :target_month, :target_amount, :sales_amount, :reward_amount, CURRENT_TIMESTAMP)'
            );
            $grant->execute([
                'team_leader_id' => $teamLeaderId,
                'target_month' => $monthStart,
                'target_amount' => $targetAmount,
                'sales_amount' => ValueFormatter::formatAmount($salesAmount),
                'reward_amount' => ValueFormatter::formatAmount($rewardAmount),
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }

            $existingGrant = $this->database->prepare(
                'SELECT id FROM team_leader_target_rewards
                 WHERE team_leader_id = :team_leader_id AND target_month = :target_month LIMIT 1'
            );
            $existingGrant->execute([
                'team_leader_id' => $teamLeaderId,
                'target_month' => $monthStart,
            ]);
            if ($existingGrant->fetchColumn() === false) {
                throw $error;
            }
        }
    }

    public function teamGrantIncentiveSettings(): array
    {
        $percentage = $this->database->query(
            'SELECT leader_share_percent FROM team_grant_incentive_settings WHERE id = 1'
        )->fetchColumn();
        if ($percentage === false) {
            throw new \RuntimeException('Team grant incentive settings are missing. Run the database schema setup.');
        }

        return ['leaderSharePercent' => (string) $percentage];
    }

    public function saveTeamGrantIncentivePercentage(string $rawPercentage, int $configuredBy): array
    {
        $percentage = trim($rawPercentage);
        if (!ValueFormatter::isValidSalesAmount($percentage) || (float) $percentage > 100) {
            throw new \InvalidArgumentException('Enter a Team Leader incentive share between 0 and 100 percent.');
        }

        $this->database->beginTransaction();
        try {
            $upsert = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT INTO team_grant_incentive_settings (id, leader_share_percent, configured_by)
                   VALUES (1, :leader_share_percent, :configured_by)
                   ON CONFLICT(id) DO UPDATE SET
                       leader_share_percent = excluded.leader_share_percent,
                       configured_by = excluded.configured_by,
                       updated_at = CURRENT_TIMESTAMP'
                : 'INSERT INTO team_grant_incentive_settings (id, leader_share_percent, configured_by)
                   VALUES (1, :leader_share_percent, :configured_by)
                   ON DUPLICATE KEY UPDATE
                       leader_share_percent = VALUES(leader_share_percent),
                       configured_by = VALUES(configured_by),
                       updated_at = CURRENT_TIMESTAMP';
            $save = $this->database->prepare($upsert);
            $save->execute([
                'leader_share_percent' => ValueFormatter::formatAmount($percentage),
                'configured_by' => $configuredBy,
            ]);

            $targets = $this->database->query(
                'SELECT team_leader_id, target_month FROM team_leader_sales_targets'
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($targets as $target) {
                $this->syncTeamGrantIncentive(
                    (int) $target['team_leader_id'],
                    substr((string) $target['target_month'], 0, 7)
                );
            }

            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return $this->teamGrantIncentiveSettings();
    }

    public function teamGrantIncentives(int $teamLeaderId, string $month): array
    {
        $leader = $this->database->prepare(
            "SELECT id FROM users WHERE id = :id AND role = 'team_leader' LIMIT 1"
        );
        $leader->execute(['id' => $teamLeaderId]);
        if ($leader->fetchColumn() === false) {
            throw new \DomainException('Team grant incentives are only available to Team Leaders.');
        }

        $monthStart = ValueFormatter::normalizeMonth($month, 'Choose a valid target month.');
        $this->syncTeamGrantIncentive($teamLeaderId, substr($monthStart, 0, 7));
        $progress = $this->teamGrantIncentiveProgress($teamLeaderId, $monthStart);
        $monthLabel = substr($monthStart, 0, 7);

        $grantQuery = $this->database->prepare(
            'SELECT team_target_amount, member_target_amount, team_member_count,
                    team_incentive_amount, leader_share_percent, leader_incentive_amount, granted_at
             FROM team_grant_incentives
             WHERE team_leader_id = :team_leader_id AND target_month = :target_month LIMIT 1'
        );
        $grantQuery->execute(['team_leader_id' => $teamLeaderId, 'target_month' => $monthStart]);
        $grant = $grantQuery->fetch(PDO::FETCH_ASSOC);

        $historyQuery = $this->database->prepare(
            'SELECT target_month, team_target_amount, member_target_amount, team_member_count,
                    team_incentive_amount, leader_share_percent, leader_incentive_amount, granted_at
             FROM team_grant_incentives
             WHERE team_leader_id = :team_leader_id
             ORDER BY target_month DESC LIMIT 24'
        );
        $historyQuery->execute(['team_leader_id' => $teamLeaderId]);

        return [
            'month' => $monthLabel,
            'teamTargetAmount' => $progress['teamTargetAmount'],
            'memberTargetAmount' => $progress['memberTargetAmount'],
            'teamSalesAmount' => ValueFormatter::formatAmount($progress['teamSalesAmount']),
            'teamIncentiveAmount' => ValueFormatter::formatAmount($progress['teamIncentiveAmount']),
            'leaderSharePercent' => $progress['leaderSharePercent'],
            'teamMemberCount' => count($progress['members']),
            'achievedMemberCount' => $progress['achievedMemberCount'],
            'allMembersReached' => $progress['allMembersReached'],
            'grant' => is_array($grant) ? [
                'teamTargetAmount' => (string) $grant['team_target_amount'],
                'memberTargetAmount' => (string) $grant['member_target_amount'],
                'teamMemberCount' => (int) $grant['team_member_count'],
                'teamIncentiveAmount' => (string) $grant['team_incentive_amount'],
                'leaderSharePercent' => (string) $grant['leader_share_percent'],
                'leaderIncentiveAmount' => (string) $grant['leader_incentive_amount'],
                'grantedAt' => (string) $grant['granted_at'],
            ] : null,
            'members' => $progress['members'],
            'grantHistory' => array_map(static fn (array $row): array => [
                'month' => substr((string) $row['target_month'], 0, 7),
                'teamTargetAmount' => (string) $row['team_target_amount'],
                'memberTargetAmount' => (string) $row['member_target_amount'],
                'teamMemberCount' => (int) $row['team_member_count'],
                'teamIncentiveAmount' => (string) $row['team_incentive_amount'],
                'leaderSharePercent' => (string) $row['leader_share_percent'],
                'leaderIncentiveAmount' => (string) $row['leader_incentive_amount'],
                'grantedAt' => (string) $row['granted_at'],
            ], $historyQuery->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    public function syncTeamGrantIncentive(int $teamLeaderId, string $month): void
    {
        $monthStart = ValueFormatter::normalizeMonth($month, 'Choose a valid target month.');
        $progress = $this->teamGrantIncentiveProgress($teamLeaderId, $monthStart);
        $percentage = (float) $progress['leaderSharePercent'];
        if (!$progress['allMembersReached'] || $percentage <= 0) {
            return;
        }

        $leaderIncentiveAmount = round($progress['teamIncentiveAmount'] * $percentage / 100, 2);
        try {
            $grant = $this->database->prepare(
                'INSERT INTO team_grant_incentives
                    (team_leader_id, target_month, team_target_amount, member_target_amount,
                     team_member_count, team_incentive_amount, leader_share_percent,
                     leader_incentive_amount, granted_at)
                 VALUES
                    (:team_leader_id, :target_month, :team_target_amount, :member_target_amount,
                     :team_member_count, :team_incentive_amount, :leader_share_percent,
                     :leader_incentive_amount, CURRENT_TIMESTAMP)'
            );
            $grant->execute([
                'team_leader_id' => $teamLeaderId,
                'target_month' => $monthStart,
                'team_target_amount' => $progress['teamTargetAmount'],
                'member_target_amount' => $progress['memberTargetAmount'],
                'team_member_count' => count($progress['members']),
                'team_incentive_amount' => ValueFormatter::formatAmount($progress['teamIncentiveAmount']),
                'leader_share_percent' => ValueFormatter::formatAmount($percentage),
                'leader_incentive_amount' => ValueFormatter::formatAmount($leaderIncentiveAmount),
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }

            $existingGrant = $this->database->prepare(
                'SELECT id FROM team_grant_incentives
                 WHERE team_leader_id = :team_leader_id AND target_month = :target_month LIMIT 1'
            );
            $existingGrant->execute([
                'team_leader_id' => $teamLeaderId,
                'target_month' => $monthStart,
            ]);
            if ($existingGrant->fetchColumn() === false) {
                throw $error;
            }
        }
    }

    private function teamGrantIncentiveProgress(int $teamLeaderId, string $monthStart): array
    {
        $targetQuery = $this->database->prepare(
            'SELECT target_amount FROM team_leader_sales_targets
             WHERE team_leader_id = :team_leader_id AND target_month = :target_month LIMIT 1'
        );
        $targetQuery->execute(['team_leader_id' => $teamLeaderId, 'target_month' => $monthStart]);
        $target = $targetQuery->fetchColumn();
        $teamTarget = $target === false ? null : (float) $target;
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');

        $membersQuery = $this->database->prepare(
            "SELECT employee.employee_id, employee.name,
                    COALESCE(SUM(sale.package_amount), 0.00) AS sales_amount,
                    COALESCE(SUM(sale.incentive_amount), 0.00) AS incentive_amount,
                    COUNT(sale.id) AS approved_sales
             FROM team_assignments AS assignment
             INNER JOIN users AS employee
               ON employee.id = assignment.telecaller_id AND employee.role = 'telecaller'
             LEFT JOIN unlock_sales AS sale
               ON sale.telecaller_id = employee.id
              AND sale.status = 'approved'
              AND sale.is_employee_credited = 1
              AND sale.emergency_assignment_id IS NULL
              AND sale.sold_on >= :month_start
              AND sale.sold_on < :month_end
             WHERE assignment.team_leader_id = :team_leader_id
             GROUP BY employee.id, employee.employee_id, employee.name
             ORDER BY sales_amount DESC, employee.name ASC"
        );
        $membersQuery->execute([
            'team_leader_id' => $teamLeaderId,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ]);
        $rows = $membersQuery->fetchAll(PDO::FETCH_ASSOC);
        $memberTarget = $teamTarget !== null && count($rows) > 0
            ? ceil(($teamTarget / count($rows)) * 100) / 100
            : null;
        $achievedMemberCount = 0;
        $teamSales = 0.0;
        $teamIncentives = 0.0;
        $members = [];
        foreach ($rows as $row) {
            $salesAmount = (float) $row['sales_amount'];
            $incentiveAmount = (float) $row['incentive_amount'];
            $targetReached = $memberTarget !== null && $salesAmount >= $memberTarget;
            if ($targetReached) {
                $achievedMemberCount++;
            }
            $teamSales += $salesAmount;
            $teamIncentives += $incentiveAmount;
            $members[] = [
                'employeeId' => (string) $row['employee_id'],
                'name' => (string) $row['name'],
                'approvedSales' => (int) $row['approved_sales'],
                'salesAmount' => ValueFormatter::formatAmount($salesAmount),
                'incentiveAmount' => ValueFormatter::formatAmount($incentiveAmount),
                'targetAmount' => $memberTarget === null ? null : ValueFormatter::formatAmount($memberTarget),
                'targetReached' => $targetReached,
                'targetProgressPercent' => $memberTarget === null || $memberTarget <= 0
                    ? 0
                    : round($salesAmount / $memberTarget * 100, 2),
            ];
        }

        return [
            'teamTargetAmount' => $teamTarget === null ? null : ValueFormatter::formatAmount($teamTarget),
            'memberTargetAmount' => $memberTarget === null ? null : ValueFormatter::formatAmount($memberTarget),
            'teamSalesAmount' => $teamSales,
            'teamIncentiveAmount' => $teamIncentives,
            'leaderSharePercent' => $this->teamGrantIncentiveSettings()['leaderSharePercent'],
            'members' => $members,
            'achievedMemberCount' => $achievedMemberCount,
            'allMembersReached' => $memberTarget !== null && count($members) > 0
                && $achievedMemberCount === count($members),
        ];
    }

    public function employeeUnlockSales(int $employeeId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, customer_reference, package_amount, is_employee_credited, emergency_assignment_id, status, incentive_amount,
                    rejection_reason, sold_on, created_at, reviewed_at
             FROM unlock_sales
             WHERE telecaller_id = :telecaller_id
             ORDER BY created_at DESC, id DESC
             LIMIT 500'
        );
        $statement->execute(['telecaller_id' => $employeeId]);
        $sales = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'customerReference' => (string) $row['customer_reference'],
            'packageAmount' => (string) $row['package_amount'],
            'isEmployeeCredited' => (bool) $row['is_employee_credited'],
            'emergencyAssignmentId' => $row['emergency_assignment_id'] === null
                ? null
                : (int) $row['emergency_assignment_id'],
            'status' => (string) $row['status'],
            'incentiveAmount' => $row['emergency_assignment_id'] !== null || $row['incentive_amount'] === null
                ? null
                : (string) $row['incentive_amount'],
            'rejectionReason' => $row['rejection_reason'] === null ? null : (string) $row['rejection_reason'],
            'soldOn' => (string) $row['sold_on'],
            'createdAt' => (string) $row['created_at'],
            'reviewedAt' => $row['reviewed_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        $earnedQuery = $this->database->prepare(
            "SELECT COALESCE(SUM(incentive_amount), 0.00)
             FROM unlock_sales
             WHERE telecaller_id = :telecaller_id AND status = 'approved'
               AND is_employee_credited = 1 AND emergency_assignment_id IS NULL"
        );
        $earnedQuery->execute(['telecaller_id' => $employeeId]);

        return [
            'sales' => $sales,
            'earnedIncentives' => (string) $earnedQuery->fetchColumn(),
        ];
    }

    public function listUnlockSalesForReview(string $status, string $type = 'all'): array
    {
        if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Choose a valid unlock sale status.');
        }
        if (!in_array($type, ['all', 'regular', 'emergency'], true)) {
            throw new \InvalidArgumentException('Choose regular, emergency, or all unlock sales.');
        }

        $typeFilter = match ($type) {
            'regular' => ' WHERE emergency_assignment_id IS NULL',
            'emergency' => ' WHERE emergency_assignment_id IS NOT NULL',
            default => '',
        };
        $summary = $this->database->query(
            "SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_count,
                COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_count,
                COALESCE(SUM(CASE WHEN status = 'pending' AND is_employee_credited = 1 THEN package_amount ELSE 0 END), 0.00) AS pending_amount,
                COALESCE(SUM(CASE WHEN status = 'approved' AND is_employee_credited = 1 THEN package_amount ELSE 0 END), 0.00) AS approved_amount
             FROM unlock_sales{$typeFilter}"
        )->fetch(PDO::FETCH_ASSOC);

        $filters = [];
        $parameters = [];
        if ($status !== 'all') {
            $filters[] = 'sale.status = :status';
            $parameters['status'] = $status;
        }
        if ($type === 'regular') {
            $filters[] = 'sale.emergency_assignment_id IS NULL';
        } elseif ($type === 'emergency') {
            $filters[] = 'sale.emergency_assignment_id IS NOT NULL';
        }
        $statusFilter = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
        $statement = $this->database->prepare(
            "SELECT sale.id, sale.customer_reference, sale.package_amount, sale.is_employee_credited,
                    sale.emergency_assignment_id, sale.status,
                    sale.incentive_amount, sale.rejection_reason, sale.sold_on,
                    sale.created_at, sale.reviewed_at, employee.employee_id, employee.name AS employee_name,
                    employee.role AS employee_role,
                    reviewer.name AS reviewer_name
             FROM unlock_sales AS sale
             INNER JOIN users AS employee ON employee.id = sale.telecaller_id
             LEFT JOIN users AS reviewer ON reviewer.id = sale.reviewed_by
             {$statusFilter}
             ORDER BY CASE sale.status WHEN 'pending' THEN 0 ELSE 1 END, sale.created_at DESC, sale.id DESC
             LIMIT 1000"
        );
        $statement->execute($parameters);

        return [
            'summary' => [
                'totalCount' => (int) $summary['total_count'],
                'pendingCount' => (int) $summary['pending_count'],
                'approvedCount' => (int) $summary['approved_count'],
                'rejectedCount' => (int) $summary['rejected_count'],
                'pendingAmount' => (string) $summary['pending_amount'],
                'approvedAmount' => (string) $summary['approved_amount'],
            ],
            'sales' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'customerReference' => (string) $row['customer_reference'],
                'packageAmount' => (string) $row['package_amount'],
                'isEmployeeCredited' => (bool) $row['is_employee_credited'],
                'emergencyAssignmentId' => $row['emergency_assignment_id'] === null
                    ? null
                    : (int) $row['emergency_assignment_id'],
                'status' => (string) $row['status'],
                'incentiveAmount' => $row['emergency_assignment_id'] !== null || $row['incentive_amount'] === null
                    ? null
                    : (string) $row['incentive_amount'],
                'rejectionReason' => $row['rejection_reason'] === null ? null : (string) $row['rejection_reason'],
                'employeeId' => (string) $row['employee_id'],
                'employeeName' => (string) $row['employee_name'],
                'employeeRole' => (string) $row['employee_role'],
                'soldOn' => (string) $row['sold_on'],
                'createdAt' => (string) $row['created_at'],
                'reviewedAt' => $row['reviewed_at'],
                'reviewerName' => $row['reviewer_name'] === null ? null : (string) $row['reviewer_name'],
            ], $statement->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    public function reviewUnlockSale(int $saleId, array $payload, int $reviewerId): array
    {
        $decision = $payload['decision'] ?? null;
        if (!is_string($decision) || !in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Choose approve or reject for this sale.');
        }
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($decision === 'rejected' && ($reason === '' || strlen($reason) > 500)) {
            throw new \InvalidArgumentException('Enter a rejection reason (maximum 500 characters).');
        }
        if ($decision === 'approved') {
            $reason = '';
        }

        $this->database->beginTransaction();
        try {
            $lockClause = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? ''
                : ' FOR UPDATE';
            $saleQuery = $this->database->prepare(
                "SELECT status, package_amount, telecaller_id, sold_on, is_employee_credited,
                        emergency_assignment_id
                 FROM unlock_sales WHERE id = :id{$lockClause}"
            );
            $saleQuery->execute(['id' => $saleId]);
            $sale = $saleQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($sale)) {
                throw new \DomainException('This unlock sale could not be found.');
            }
            if ($sale['status'] !== 'pending') {
                throw new \DomainException('This unlock sale has already been reviewed.');
            }

            $incentiveAmount = null;
            $isEmergencySale = $sale['emergency_assignment_id'] !== null;
            if ($decision === 'approved' && (bool) $sale['is_employee_credited'] && !$isEmergencySale) {
                $packageQuery = $this->database->prepare(
                    'SELECT incentive_amount FROM unlock_package_incentives
                     WHERE package_amount = :package_amount FOR UPDATE'
                );
                $packageQuery->execute(['package_amount' => $sale['package_amount']]);
                $incentiveAmount = $packageQuery->fetchColumn();
                if ($incentiveAmount === false || $incentiveAmount === null) {
                    throw new \DomainException('Configure this package incentive before approving the sale.');
                }
            }

            $update = $this->database->prepare(
                'UPDATE unlock_sales
                 SET status = :status, incentive_amount = :incentive_amount,
                     rejection_reason = :rejection_reason, reviewed_by = :reviewed_by,
                     reviewed_at = CURRENT_TIMESTAMP
                 WHERE id = :id AND status = :pending_status'
            );
            $update->execute([
                'status' => $decision,
                'incentive_amount' => $incentiveAmount,
                'rejection_reason' => $decision === 'rejected' ? $reason : null,
                'reviewed_by' => $reviewerId,
                'id' => $saleId,
                'pending_status' => 'pending',
            ]);
            if ($update->rowCount() !== 1) {
                throw new \DomainException('This unlock sale has already been reviewed.');
            }
            if ($decision === 'approved' && (bool) $sale['is_employee_credited'] && !$isEmergencySale) {
                $assignment = $this->database->prepare(
                    "SELECT assignment.team_leader_id
                     FROM team_assignments AS assignment
                     INNER JOIN users AS employee
                       ON employee.id = assignment.telecaller_id AND employee.role = 'telecaller'
                     WHERE assignment.telecaller_id = :telecaller_id LIMIT 1"
                );
                $assignment->execute(['telecaller_id' => $sale['telecaller_id']]);
                $teamLeaderId = $assignment->fetchColumn();
                if ($teamLeaderId !== false) {
                    $this->syncTeamTargetReward(
                        (int) $teamLeaderId,
                        substr((string) $sale['sold_on'], 0, 7)
                    );
                    $this->syncTeamGrantIncentive(
                        (int) $teamLeaderId,
                        substr((string) $sale['sold_on'], 0, 7)
                    );
                }
            }
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return [
            'id' => $saleId,
            'status' => $decision,
            'incentiveAmount' => $incentiveAmount === null ? null : (string) $incentiveAmount,
            'isEmployeeCredited' => (bool) $sale['is_employee_credited'],
            'rejectionReason' => $decision === 'rejected' ? $reason : null,
        ];
    }

    public function employeeIncentives(int $employeeId): array
    {
        $statement = $this->database->prepare(
            "SELECT id, customer_reference, package_amount, incentive_amount, sold_on, reviewed_at
             FROM unlock_sales
            WHERE telecaller_id = :telecaller_id AND status = 'approved'
              AND is_employee_credited = 1 AND emergency_assignment_id IS NULL
             ORDER BY reviewed_at DESC, id DESC
             LIMIT 500"
        );
        $statement->execute(['telecaller_id' => $employeeId]);
        $earnedQuery = $this->database->prepare(
            "SELECT COALESCE(SUM(incentive_amount), 0.00)
             FROM unlock_sales
            WHERE telecaller_id = :telecaller_id AND status = 'approved'
              AND is_employee_credited = 1 AND emergency_assignment_id IS NULL"
        );
        $earnedQuery->execute(['telecaller_id' => $employeeId]);

        return [
            'totalEarned' => (string) $earnedQuery->fetchColumn(),
            'incentives' => array_map(static fn (array $row): array => [
                'saleId' => (int) $row['id'],
                'customerReference' => (string) $row['customer_reference'],
                'packageAmount' => (string) $row['package_amount'],
                'incentiveAmount' => (string) $row['incentive_amount'],
                'soldOn' => (string) $row['sold_on'],
                'approvedAt' => (string) $row['reviewed_at'],
            ], $statement->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    public function subEditorPaidIncentives(string $month): array
    {
        $monthStart = ValueFormatter::normalizeMonth($month, 'Choose a valid incentive month.');
        $nextMonthStart = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');
        $statement = $this->database->prepare(
            "SELECT employee.id, employee.employee_id, employee.name, employee.role,
                    COALESCE(sales.earned_amount, 0.00)
                        + COALESCE(reward.earned_amount, 0.00)
                        + COALESCE(grant_record.earned_amount, 0.00) AS earned_amount,
                    COALESCE(sales.approved_sales, 0) AS approved_sales,
                    payout.amount AS paid_amount, payout.paid_at
             FROM users AS employee
             LEFT JOIN (
                SELECT telecaller_id AS employee_id, SUM(incentive_amount) AS earned_amount,
                       COUNT(*) AS approved_sales
                FROM unlock_sales
                WHERE status = 'approved' AND is_employee_credited = 1
                  AND emergency_assignment_id IS NULL
                  AND sold_on >= :sales_month_start AND sold_on < :sales_next_month_start
                GROUP BY telecaller_id
             ) AS sales ON sales.employee_id = employee.id
             LEFT JOIN (
                SELECT team_leader_id AS employee_id, SUM(reward_amount) AS earned_amount
                FROM team_leader_target_rewards
                WHERE target_month >= :reward_month_start AND target_month < :reward_next_month_start
                GROUP BY team_leader_id
             ) AS reward ON reward.employee_id = employee.id
             LEFT JOIN (
                SELECT team_leader_id AS employee_id, SUM(leader_incentive_amount) AS earned_amount
                FROM team_grant_incentives
                WHERE target_month >= :grant_month_start AND target_month < :grant_next_month_start
                GROUP BY team_leader_id
             ) AS grant_record ON grant_record.employee_id = employee.id
             LEFT JOIN employee_incentive_payouts AS payout
               ON payout.employee_id = employee.id AND payout.payout_month = :payout_month
             WHERE employee.role IN ('team_leader', 'telecaller')
             ORDER BY employee.name ASC, employee.id ASC"
        );
        $statement->execute([
            'sales_month_start' => $monthStart,
            'sales_next_month_start' => $nextMonthStart,
            'reward_month_start' => $monthStart,
            'reward_next_month_start' => $nextMonthStart,
            'grant_month_start' => $monthStart,
            'grant_next_month_start' => $nextMonthStart,
            'payout_month' => $monthStart,
        ]);
        $employees = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $earnedAmount = (float) $row['earned_amount'];
            if ($earnedAmount <= 0) {
                continue;
            }
            $employees[] = [
                'id' => (int) $row['id'],
                'employeeId' => (string) $row['employee_id'],
                'name' => (string) $row['name'],
                'role' => (string) $row['role'],
                'approvedSales' => (int) $row['approved_sales'],
                'earnedAmount' => ValueFormatter::formatAmount($earnedAmount),
                'paidAmount' => $row['paid_amount'] === null ? null : (string) $row['paid_amount'],
                'paidAt' => $row['paid_at'],
                'isPaid' => $row['paid_amount'] !== null,
            ];
        }

        return [
            'month' => substr($monthStart, 0, 7),
            'employees' => $employees,
        ];
    }

    public function markMonthlyIncentivePaid(int $employeeId, string $month, int $paidBy): array
    {
        if ($employeeId < 1) {
            throw new \InvalidArgumentException('Choose a valid employee.');
        }

        $report = $this->subEditorPaidIncentives($month);
        $employee = null;
        foreach ($report['employees'] as $row) {
            if ($row['id'] === $employeeId) {
                $employee = $row;
                break;
            }
        }
        if ($employee === null) {
            throw new \DomainException('No earned incentive is available for this employee and month.');
        }
        if ($employee['isPaid']) {
            throw new \DomainException('This employee’s incentive has already been marked paid for the month.');
        }

        $monthStart = ValueFormatter::normalizeMonth($month, 'Choose a valid incentive month.');
        try {
            $statement = $this->database->prepare(
                'INSERT INTO employee_incentive_payouts (employee_id, payout_month, amount, paid_by)
                 VALUES (:employee_id, :payout_month, :amount, :paid_by)'
            );
            $statement->execute([
                'employee_id' => $employeeId,
                'payout_month' => $monthStart,
                'amount' => $employee['earnedAmount'],
                'paid_by' => $paidBy,
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            throw new \DomainException('This employee’s incentive has already been marked paid for the month.');
        }

        $paidAtQuery = $this->database->prepare(
            'SELECT paid_at FROM employee_incentive_payouts
             WHERE employee_id = :employee_id AND payout_month = :payout_month'
        );
        $paidAtQuery->execute([
            'employee_id' => $employeeId,
            'payout_month' => $monthStart,
        ]);

        return [
            'employeeId' => $employee['employeeId'],
            'name' => $employee['name'],
            'month' => substr($monthStart, 0, 7),
            'paidAmount' => $employee['earnedAmount'],
            'paidAt' => (string) $paidAtQuery->fetchColumn(),
        ];
    }

    public function subEditorAchievedRewards(): array
    {
        $statement = $this->database->query(
            "SELECT leader.employee_id, leader.name AS leader_name, reward.target_month,
                    reward.target_amount, reward.achieved_sales_amount, reward.reward_amount, reward.granted_at
             FROM team_leader_target_rewards AS reward
             INNER JOIN users AS leader
               ON leader.id = reward.team_leader_id AND leader.role = 'team_leader'
             ORDER BY reward.target_month DESC, leader.name ASC, reward.id DESC
             LIMIT 1000"
        );

        return array_map(static fn (array $row): array => [
            'employeeId' => (string) $row['employee_id'],
            'leaderName' => (string) $row['leader_name'],
            'month' => substr((string) $row['target_month'], 0, 7),
            'targetAmount' => (string) $row['target_amount'],
            'achievedSalesAmount' => (string) $row['achieved_sales_amount'],
            'rewardAmount' => (string) $row['reward_amount'],
            'grantedAt' => (string) $row['granted_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function subEditorGrantedRewards(): array
    {
        $statement = $this->database->query(
            "SELECT leader.employee_id, leader.name AS leader_name, grant_record.target_month,
                    grant_record.team_target_amount, grant_record.team_member_count,
                    grant_record.team_incentive_amount, grant_record.leader_share_percent,
                    grant_record.leader_incentive_amount, grant_record.granted_at
             FROM team_grant_incentives AS grant_record
             INNER JOIN users AS leader
               ON leader.id = grant_record.team_leader_id AND leader.role = 'team_leader'
             ORDER BY grant_record.target_month DESC, leader.name ASC, grant_record.id DESC
             LIMIT 1000"
        );

        return array_map(static fn (array $row): array => [
            'employeeId' => (string) $row['employee_id'],
            'leaderName' => (string) $row['leader_name'],
            'month' => substr((string) $row['target_month'], 0, 7),
            'teamTargetAmount' => (string) $row['team_target_amount'],
            'teamMemberCount' => (int) $row['team_member_count'],
            'teamIncentiveAmount' => (string) $row['team_incentive_amount'],
            'leaderSharePercent' => (string) $row['leader_share_percent'],
            'leaderIncentiveAmount' => (string) $row['leader_incentive_amount'],
            'grantedAt' => (string) $row['granted_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function subEditorEarnedIncentives(): array
    {
        $statement = $this->database->query(
            "SELECT employee.employee_id, employee.name, employee.role,
                    COUNT(sale.id) AS approved_sales,
                    SUM(sale.incentive_amount) AS total_incentive
             FROM unlock_sales AS sale
             INNER JOIN users AS employee ON employee.id = sale.telecaller_id
             WHERE sale.status = 'approved'
               AND sale.is_employee_credited = 1
               AND sale.emergency_assignment_id IS NULL
               AND employee.role IN ('telecaller', 'team_leader')
             GROUP BY employee.id, employee.employee_id, employee.name, employee.role
             ORDER BY total_incentive DESC, employee.name ASC"
        );
        $incentiveQuery = $this->database->query(
            "SELECT sale.id, employee.employee_id, employee.name AS employee_name, employee.role,
                    sale.customer_reference, sale.package_amount, sale.incentive_amount, sale.reviewed_at
             FROM unlock_sales AS sale
             INNER JOIN users AS employee ON employee.id = sale.telecaller_id
             WHERE sale.status = 'approved'
               AND sale.is_employee_credited = 1
               AND sale.emergency_assignment_id IS NULL
               AND employee.role IN ('telecaller', 'team_leader')
             ORDER BY sale.reviewed_at DESC, sale.id DESC
             LIMIT 1000"
        );
        $totalQuery = $this->database->query(
            "SELECT COALESCE(SUM(incentive_amount), 0.00) AS total_earned, COUNT(*) AS approved_sales
             FROM unlock_sales
             WHERE status = 'approved' AND is_employee_credited = 1
               AND emergency_assignment_id IS NULL"
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'totalEarned' => (string) $totalQuery['total_earned'],
            'approvedSalesCount' => (int) $totalQuery['approved_sales'],
            'employees' => array_map(static fn (array $row): array => [
                'employeeId' => (string) $row['employee_id'],
                'name' => (string) $row['name'],
                'role' => (string) $row['role'],
                'approvedSales' => (int) $row['approved_sales'],
                'totalIncentive' => (string) $row['total_incentive'],
            ], $statement->fetchAll(PDO::FETCH_ASSOC)),
            'incentives' => array_map(static fn (array $row): array => [
                'saleId' => (int) $row['id'],
                'employeeId' => (string) $row['employee_id'],
                'employeeName' => (string) $row['employee_name'],
                'role' => (string) $row['role'],
                'customerReference' => (string) $row['customer_reference'],
                'packageAmount' => (string) $row['package_amount'],
                'incentiveAmount' => (string) $row['incentive_amount'],
                'approvedAt' => (string) $row['reviewed_at'],
            ], $incentiveQuery->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }
}
