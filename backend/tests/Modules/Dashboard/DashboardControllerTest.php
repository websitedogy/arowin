<?php

declare(strict_types=1);

use Arowin\Modules\Dashboard\DashboardController;
use Arowin\Shared\AppTime;
use PHPUnit\Framework\TestCase;

final class DashboardControllerTest extends TestCase
{
    public function testSubEditorSummaryIncludesLiveFeatureCounts(): void
    {
        $database = $this->createDatabase();
        $now = AppTime::now();
        $sales = $database->prepare('INSERT INTO unlock_sales (status, sold_on) VALUES (:status, :sold_on)');
        foreach ([
            ['approved', $now->format('Y-m-d')],
            ['approved', $now->modify('first day of last month')->format('Y-m-d')],
            ['approved', '2020-01-01'],
            ['pending', $now->format('Y-m-d')],
        ] as [$status, $soldOn]) {
            $sales->execute(['status' => $status, 'sold_on' => $soldOn]);
        }

        $database->exec("INSERT INTO users (role, is_active) VALUES ('team_leader', 1), ('telecaller', 1)");
        $database->exec('INSERT INTO attendance_records DEFAULT VALUES');
        $database->exec('INSERT INTO attendance_records DEFAULT VALUES');
        $target = $database->prepare('INSERT INTO team_leader_sales_targets (target_month) VALUES (?)');
        $target->execute([$now->modify('first day of this month')->format('Y-m-d')]);
        $target->execute([$now->modify('first day of last month')->format('Y-m-d')]);
        $database->exec('INSERT INTO team_leader_target_rewards DEFAULT VALUES');
        $database->exec('INSERT INTO team_grant_incentives DEFAULT VALUES');
        $database->exec('INSERT INTO team_grant_incentives DEFAULT VALUES');
        $database->exec('INSERT INTO employee_incentive_payouts DEFAULT VALUES');
        $database->exec('INSERT INTO notification_trigger_settings (enabled) VALUES (1), (0)');
        $database->exec('INSERT INTO telecaller_leads DEFAULT VALUES');
        $database->exec('INSERT INTO telecaller_leads DEFAULT VALUES');
        $database->exec('INSERT INTO telecaller_leads DEFAULT VALUES');

        $dashboard = (new DashboardController($database))->summary('sub_editor')['dashboard'];

        self::assertSame(2, $dashboard['kpis']['employees']);
        self::assertSame([
            'attendance_tracking' => 2,
            'total_sales' => 3,
            'this_week_sales' => 1,
            'incentive_earned' => 3,
            'incentive_paid' => 1,
            'last_month_sales' => 1,
            'target_setup' => 1,
            'achieved_rewards' => 1,
            'granted_rewards' => 2,
            'notification_trigger' => 1,
            'all_leads' => 3,
        ], $dashboard['featureCounts']);
    }

    public function testFeatureCountsAreNotIncludedForOtherRoles(): void
    {
        $dashboard = (new DashboardController($this->createDatabase()))->summary('team_leader')['dashboard'];

        self::assertArrayNotHasKey('featureCounts', $dashboard);
    }

    public function testFeatureCountsSupportLegacyUnlockSalesWithoutEmployeeCreditColumn(): void
    {
        $database = $this->createDatabase(false);
        $sales = $database->prepare('INSERT INTO unlock_sales (status, sold_on) VALUES (:status, :sold_on)');
        $sales->execute(['status' => 'approved', 'sold_on' => AppTime::now()->format('Y-m-d')]);
        $sales->execute(['status' => 'pending', 'sold_on' => AppTime::now()->format('Y-m-d')]);

        $counts = (new DashboardController($database))->summary('sub_editor')['dashboard']['featureCounts'];

        self::assertSame(1, $counts['total_sales']);
        self::assertSame(1, $counts['this_week_sales']);
        self::assertSame(1, $counts['incentive_earned']);
    }

    public function testEmergencySalesDoNotCountAsIncentiveEligibleFeatureSales(): void
    {
        $database = $this->createDatabase(true, true);
        $date = AppTime::now()->format('Y-m-d');
        $sales = $database->prepare(
            'INSERT INTO unlock_sales (status, sold_on, is_employee_credited, emergency_assignment_id)
             VALUES (:status, :sold_on, 1, :emergency_assignment_id)'
        );
        $sales->execute(['status' => 'approved', 'sold_on' => $date, 'emergency_assignment_id' => null]);
        $sales->execute(['status' => 'approved', 'sold_on' => $date, 'emergency_assignment_id' => 42]);

        $counts = (new DashboardController($database))->summary('sub_editor')['dashboard']['featureCounts'];

        self::assertSame(1, $counts['total_sales']);
        self::assertSame(1, $counts['this_week_sales']);
        self::assertSame(1, $counts['incentive_earned']);
    }

    private function createDatabase(
        bool $includeEmployeeCreditColumn = true,
        bool $includeEmergencyAssignmentColumn = false
    ): \PDO
    {
        $database = new \PDO('sqlite::memory:');
        $database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE users (role TEXT, is_active INTEGER)');
        $database->exec('CREATE TABLE attendance_records (id INTEGER PRIMARY KEY)');
        $employeeCreditColumn = $includeEmployeeCreditColumn
            ? ', is_employee_credited INTEGER NOT NULL DEFAULT 1'
            : '';
        $emergencyAssignmentColumn = $includeEmergencyAssignmentColumn
            ? ', emergency_assignment_id INTEGER'
            : '';
        $database->exec("CREATE TABLE unlock_sales (
            status TEXT,
            sold_on TEXT{$employeeCreditColumn}{$emergencyAssignmentColumn}
        )");
        $database->exec('CREATE TABLE team_leader_sales_targets (target_month TEXT)');
        $database->exec('CREATE TABLE team_leader_target_rewards (id INTEGER PRIMARY KEY)');
        $database->exec('CREATE TABLE team_grant_incentives (id INTEGER PRIMARY KEY)');
        $database->exec('CREATE TABLE employee_incentive_payouts (id INTEGER PRIMARY KEY)');
        $database->exec('CREATE TABLE notification_trigger_settings (enabled INTEGER)');
        $database->exec('CREATE TABLE telecaller_leads (id INTEGER PRIMARY KEY)');

        return $database;
    }
}
