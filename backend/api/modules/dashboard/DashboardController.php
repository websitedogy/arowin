<?php

declare(strict_types=1);

namespace Arowin\Modules\Dashboard;

use Arowin\EmployeeRoles;
use Arowin\Shared\AppTime;
use PDO;

final class DashboardController
{
    private array $unlockSalesColumns = [];

    public function __construct(private readonly PDO $database)
    {
    }

    public function summary(string $role): array
    {
        $statement = $this->database->query(
            'SELECT role, COUNT(*) AS total
             FROM users
             WHERE is_active = 1
             GROUP BY role'
        );
        $counts = array_fill_keys(array_keys(EmployeeRoles::LABELS), 0);
        foreach ($statement->fetchAll() as $row) {
            $counts[$row['role']] = (int) $row['total'];
        }

        $dashboard = [
            'module' => $role,
            'kpis' => [
                'employees' => array_sum($counts),
                'sub_editors' => $counts['sub_editor'],
                'team_leaders' => $counts['team_leader'],
                'telecallers' => $counts['telecaller'],
            ],
        ];

        if ($role === 'sub_editor') {
            $dashboard['featureCounts'] = $this->featureCounts();
        }

        return [
            'success' => true,
            'dashboard' => $dashboard,
        ];
    }

    private function featureCounts(): array
    {
        $now = AppTime::now();
        // Legacy schemas counted every approved sale before employee attribution was added.
        $employeeCreditFilter = $this->hasUnlockSalesColumn('is_employee_credited')
            ? ' AND is_employee_credited = 1'
            : '';
        $regularSaleFilter = $this->hasUnlockSalesColumn('emergency_assignment_id')
            ? ' AND emergency_assignment_id IS NULL'
            : '';
        $statement = $this->database->prepare(
            "SELECT
                (SELECT COUNT(*) FROM attendance_records) AS attendance_tracking,
                (SELECT COUNT(*) FROM unlock_sales
                 WHERE status = 'approved'{$employeeCreditFilter}{$regularSaleFilter}
                   AND sold_on < :total_sales_through) AS total_sales,
                (SELECT COUNT(*) FROM unlock_sales
                 WHERE status = 'approved'
                   {$employeeCreditFilter}{$regularSaleFilter}
                   AND sold_on >= :week_start AND sold_on < :week_through) AS this_week_sales,
                (SELECT COUNT(*) FROM unlock_sales
                 WHERE status = 'approved'{$employeeCreditFilter}{$regularSaleFilter}) AS incentive_earned,
                (SELECT COUNT(*) FROM unlock_sales
                 WHERE status = 'approved'
                   {$employeeCreditFilter}{$regularSaleFilter}
                   AND sold_on >= :last_month_start AND sold_on < :this_month_start) AS last_month_sales,
                (SELECT COUNT(*) FROM team_leader_sales_targets
                 WHERE target_month = :target_month) AS target_setup,
                (SELECT COUNT(*) FROM team_leader_target_rewards) AS achieved_rewards,
                (SELECT COUNT(*) FROM team_grant_incentives) AS granted_rewards,
                (SELECT COUNT(*) FROM employee_incentive_payouts) AS incentive_paid,
                (SELECT COUNT(*) FROM notification_trigger_settings WHERE enabled = 1) AS notification_trigger,
                (SELECT COUNT(*) FROM telecaller_leads) AS all_leads"
        );
        $monthStart = $now->modify('first day of this month')->format('Y-m-d');
        $statement->execute([
            'total_sales_through' => $now->modify('+1 day')->format('Y-m-d'),
            'week_start' => $now->modify('monday this week')->format('Y-m-d'),
            'week_through' => $now->modify('+1 day')->format('Y-m-d'),
            'last_month_start' => $now->modify('first day of last month')->format('Y-m-d'),
            'this_month_start' => $monthStart,
            'target_month' => $monthStart,
        ]);
        $counts = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($counts)) {
            throw new \RuntimeException('Dashboard feature counts could not be loaded.');
        }

        return [
            'attendance_tracking' => (int) $counts['attendance_tracking'],
            'total_sales' => (int) $counts['total_sales'],
            'this_week_sales' => (int) $counts['this_week_sales'],
            'incentive_earned' => (int) $counts['incentive_earned'],
            'incentive_paid' => (int) $counts['incentive_paid'],
            'last_month_sales' => (int) $counts['last_month_sales'],
            'target_setup' => (int) $counts['target_setup'],
            'achieved_rewards' => (int) $counts['achieved_rewards'],
            'granted_rewards' => (int) $counts['granted_rewards'],
            'notification_trigger' => (int) $counts['notification_trigger'],
            'all_leads' => (int) $counts['all_leads'],
        ];
    }

    private function hasUnlockSalesColumn(string $columnName): bool
    {
        if (array_key_exists($columnName, $this->unlockSalesColumns)) {
            return $this->unlockSalesColumns[$columnName];
        }

        $driver = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $columns = $this->database->query('PRAGMA table_info(unlock_sales)')->fetchAll(PDO::FETCH_ASSOC);
            $this->unlockSalesColumns[$columnName] = in_array($columnName, array_column($columns, 'name'), true);
            return $this->unlockSalesColumns[$columnName];
        }

        if ($driver !== 'mysql') {
            throw new \RuntimeException('Employee credit column detection is not supported for this database driver.');
        }

        $statement = $this->database->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => 'unlock_sales',
            'column_name' => $columnName,
        ]);
        $this->unlockSalesColumns[$columnName] = (int) $statement->fetchColumn() > 0;
        return $this->unlockSalesColumns[$columnName];
    }
}
