<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

use Arowin\Shared\AppTime;
use Arowin\Shared\ValueFormatter;
use DateTimeImmutable;
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
             WHERE telecaller_id = :user_id AND status = 'approved'
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
            ? "WHERE sales.status = 'approved' AND sales.sold_on < :end_date"
            : "WHERE sales.status = 'approved' AND sales.sold_on >= :start_date AND sales.sold_on < :end_date";
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

        return [
            'period' => $period,
            'periodLabel' => $periodLabel,
            'startDate' => $startDate,
            'throughDate' => $startDate === null ? null : (new DateTimeImmutable($endDate))->modify('-1 day')->format('Y-m-d'),
            'totalSales' => (string) $totalStatement->fetchColumn(),
            'salespersonCount' => count($salespeople),
            'salesEntries' => array_sum(array_column($salespeople, 'salesEntries')),
            'salespeople' => $salespeople,
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

    public function submitUnlockSale(): array
    {
        throw new \DomainException('Arowin Global customer and package lookup is not configured. Sales cannot be submitted until a verified customer package can be loaded.');
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

    public function employeeUnlockSales(int $employeeId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, customer_reference, package_amount, status, incentive_amount,
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
            'status' => (string) $row['status'],
            'incentiveAmount' => $row['incentive_amount'] === null ? null : (string) $row['incentive_amount'],
            'rejectionReason' => $row['rejection_reason'] === null ? null : (string) $row['rejection_reason'],
            'soldOn' => (string) $row['sold_on'],
            'createdAt' => (string) $row['created_at'],
            'reviewedAt' => $row['reviewed_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));

        $earnedQuery = $this->database->prepare(
            "SELECT COALESCE(SUM(incentive_amount), 0.00)
             FROM unlock_sales
             WHERE telecaller_id = :telecaller_id AND status = 'approved'"
        );
        $earnedQuery->execute(['telecaller_id' => $employeeId]);

        return [
            'sales' => $sales,
            'earnedIncentives' => (string) $earnedQuery->fetchColumn(),
        ];
    }

    public function listUnlockSalesForReview(string $status): array
    {
        if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
            throw new \InvalidArgumentException('Choose a valid unlock sale status.');
        }

        $summary = $this->database->query(
            "SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved_count,
                COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_count,
                COALESCE(SUM(CASE WHEN status = 'pending' THEN package_amount ELSE 0 END), 0.00) AS pending_amount,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN package_amount ELSE 0 END), 0.00) AS approved_amount
             FROM unlock_sales"
        )->fetch(PDO::FETCH_ASSOC);

        $statusFilter = $status === 'all' ? '' : 'WHERE sale.status = :status';
        $statement = $this->database->prepare(
            "SELECT sale.id, sale.customer_reference, sale.package_amount, sale.status,
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
        if ($status === 'all') {
            $statement->execute();
        } else {
            $statement->execute(['status' => $status]);
        }

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
                'status' => (string) $row['status'],
                'incentiveAmount' => $row['incentive_amount'] === null ? null : (string) $row['incentive_amount'],
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
            $saleQuery = $this->database->prepare(
                'SELECT status, package_amount
                 FROM unlock_sales WHERE id = :id FOR UPDATE'
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
            if ($decision === 'approved') {
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
            'rejectionReason' => $decision === 'rejected' ? $reason : null,
        ];
    }

    public function employeeIncentives(int $employeeId): array
    {
        $statement = $this->database->prepare(
            "SELECT id, customer_reference, package_amount, incentive_amount, sold_on, reviewed_at
             FROM unlock_sales
             WHERE telecaller_id = :telecaller_id AND status = 'approved'
             ORDER BY reviewed_at DESC, id DESC
             LIMIT 500"
        );
        $statement->execute(['telecaller_id' => $employeeId]);
        $earnedQuery = $this->database->prepare(
            "SELECT COALESCE(SUM(incentive_amount), 0.00)
             FROM unlock_sales
             WHERE telecaller_id = :telecaller_id AND status = 'approved'"
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

    public function subEditorEarnedIncentives(): array
    {
        $statement = $this->database->query(
            "SELECT employee.employee_id, employee.name, employee.role,
                    COUNT(sale.id) AS approved_sales,
                    SUM(sale.incentive_amount) AS total_incentive
             FROM unlock_sales AS sale
             INNER JOIN users AS employee ON employee.id = sale.telecaller_id
             WHERE sale.status = 'approved'
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
               AND employee.role IN ('telecaller', 'team_leader')
             ORDER BY sale.reviewed_at DESC, sale.id DESC
             LIMIT 1000"
        );
        $totalQuery = $this->database->query(
            "SELECT COALESCE(SUM(incentive_amount), 0.00) AS total_earned, COUNT(*) AS approved_sales
             FROM unlock_sales WHERE status = 'approved'"
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
