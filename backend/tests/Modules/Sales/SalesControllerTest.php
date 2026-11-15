<?php

declare(strict_types=1);

use Arowin\Modules\Sales\SalesController;
use Arowin\Shared\AppTime;
use PHPUnit\Framework\TestCase;

final class SalesControllerTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT,
            name TEXT,
            role TEXT,
            is_active INTEGER,
            email TEXT,
            password_hash TEXT
        )');
        $this->database->exec('CREATE TABLE team_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            telecaller_id INTEGER,
            team_leader_id INTEGER
        )');
        $this->database->exec('CREATE TABLE team_leader_sales_targets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_leader_id INTEGER,
            target_month TEXT,
            target_amount TEXT
        )');
        $this->database->exec('CREATE TABLE team_leader_target_reward_settings (
            id INTEGER PRIMARY KEY,
            reward_amount TEXT NOT NULL DEFAULT "0.00",
            configured_by INTEGER,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->exec('CREATE TABLE team_leader_target_rewards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_leader_id INTEGER NOT NULL,
            target_month TEXT NOT NULL,
            target_amount TEXT NOT NULL,
            achieved_sales_amount TEXT NOT NULL,
            reward_amount TEXT NOT NULL,
            granted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (team_leader_id, target_month)
        )');
        $this->database->exec('CREATE TABLE team_grant_incentive_settings (
            id INTEGER PRIMARY KEY,
            leader_share_percent TEXT NOT NULL DEFAULT "0.00",
            configured_by INTEGER,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->exec('CREATE TABLE team_grant_incentives (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_leader_id INTEGER NOT NULL,
            target_month TEXT NOT NULL,
            team_target_amount TEXT NOT NULL,
            member_target_amount TEXT NOT NULL,
            team_member_count INTEGER NOT NULL,
            team_incentive_amount TEXT NOT NULL,
            leader_share_percent TEXT NOT NULL,
            leader_incentive_amount TEXT NOT NULL,
            granted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (team_leader_id, target_month)
        )');
        $this->database->exec('CREATE TABLE unlock_sales (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            telecaller_id INTEGER,
            package_amount TEXT,
            is_employee_credited INTEGER NOT NULL DEFAULT 1,
            emergency_assignment_id INTEGER,
            incentive_amount TEXT,
            sold_on TEXT,
            status TEXT,
            rejection_reason TEXT,
            reviewed_by INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TEXT,
            customer_reference TEXT
        )');
        $this->database->exec('CREATE TABLE emergency_sales_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            package_amount TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            note TEXT NOT NULL DEFAULT \'\',
            assigned_by INTEGER,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->exec('CREATE TABLE employee_incentive_payouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL,
            payout_month TEXT NOT NULL,
            amount TEXT NOT NULL,
            paid_by INTEGER,
            paid_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (employee_id, payout_month)
        )');
        $this->database->exec('CREATE TABLE employee_salaries (
            user_id INTEGER PRIMARY KEY,
            salary_amount TEXT NOT NULL
        )');

        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (1, '100001', 'Team Leader', 'team_leader', 1, 'leader@example.com', 'hash')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (2, '200001', 'Telecaller', 'telecaller', 1, 'telecaller@example.com', 'hash')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (3, '200002', 'Second Telecaller', 'telecaller', 1, 'second@example.com', 'hash')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (4, '100002', 'Other Team Leader', 'team_leader', 1, 'other@example.com', 'hash')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (5, '200003', 'Other Team Telecaller', 'telecaller', 1, 'other-caller@example.com', 'hash')");
        $this->database->exec("INSERT INTO employee_salaries (user_id, salary_amount) VALUES (2, '30000.00')");
        $this->database->exec("INSERT INTO team_assignments (telecaller_id, team_leader_id) VALUES (2, 1)");
        $this->database->exec("INSERT INTO team_assignments (telecaller_id, team_leader_id) VALUES (3, 1)");
        $this->database->exec("INSERT INTO team_assignments (telecaller_id, team_leader_id) VALUES (5, 4)");
        $this->database->exec("INSERT INTO team_leader_sales_targets (team_leader_id, target_month, target_amount) VALUES (1, '2025-01-01', '500.00')");
        $this->database->exec("INSERT INTO team_leader_target_reward_settings (id, reward_amount) VALUES (1, '0.00')");
        $this->database->exec("INSERT INTO team_grant_incentive_settings (id, leader_share_percent) VALUES (1, '0.00')");
        $this->database->exec("INSERT INTO unlock_sales (telecaller_id, package_amount, incentive_amount, sold_on, status, reviewed_at, customer_reference) VALUES (2, '150.00', '10.00', '2025-01-05', 'approved', '2025-01-06', 'Customer A')");
        $this->database->exec("INSERT INTO unlock_sales (telecaller_id, package_amount, incentive_amount, sold_on, status, reviewed_at, customer_reference) VALUES (2, '250.00', '20.00', '2025-01-10', 'approved', '2025-01-11', 'Customer B')");
        $this->database->exec("INSERT INTO unlock_sales (telecaller_id, package_amount, incentive_amount, sold_on, status, customer_reference) VALUES (2, '75.00', NULL, '2025-01-10', 'pending', 'Pending Customer')");
        $this->database->exec("INSERT INTO unlock_sales (telecaller_id, package_amount, incentive_amount, sold_on, status, reviewed_at, customer_reference) VALUES (5, '150.00', '99.00', '2025-01-05', 'approved', '2025-01-06', 'Other Team Customer')");
    }

    public function testPerformanceReportsTeamLeaderDailySalesAndTarget(): void
    {
        $controller = new SalesController($this->database);

        $result = $controller->performance(1, 'team_leader', '2025-01');

        $this->assertSame('team', $result['scope']);
        $this->assertSame('500.00', $result['monthlyTarget']);
        $this->assertSame(2, $result['memberCount']);
        $this->assertCount(2, $result['dailySales']);
        $this->assertSame('2025-01-05', $result['dailySales'][0]['date']);
        $this->assertSame('150', $result['dailySales'][0]['amount']);
    }

    public function testPerformanceRejectsUnsupportedRole(): void
    {
        $controller = new SalesController($this->database);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Sales performance is only available to Team Leaders and Telecallers.');

        $controller->performance(1, 'sub_editor', '2025-01');
    }

    public function testSubEditorSummaryTotalsOnlyApprovedSales(): void
    {
        $this->database->exec(
            "INSERT INTO unlock_sales
                (telecaller_id, package_amount, emergency_assignment_id, sold_on, status, customer_reference)
             VALUES (2, '1000.00', 42, '2025-01-12', 'approved', 'Emergency Sale')"
        );
        $controller = new SalesController($this->database);

        $result = $controller->subEditorSummary('total');

        $this->assertSame('total', $result['period']);
        $this->assertSame('550', $result['totalSales']);
        $this->assertSame(2, $result['salespersonCount']);
        $this->assertSame(3, $result['salesEntries']);
    }

    public function testSubEditorUnlockSaleReviewFiltersRegularAndEmergencySales(): void
    {
        $this->database->exec(
            "INSERT INTO unlock_sales
                (telecaller_id, package_amount, emergency_assignment_id, sold_on, status, customer_reference)
             VALUES (2, '1000.00', 42, '2025-01-12', 'pending', 'Emergency Sale')"
        );
        $controller = new SalesController($this->database);

        $regularSales = $controller->listUnlockSalesForReview('all', 'regular')['sales'];
        $emergencySales = $controller->listUnlockSalesForReview('all', 'emergency')['sales'];

        self::assertCount(4, $regularSales);
        self::assertCount(1, $emergencySales);
        self::assertNotNull($emergencySales[0]['emergencyAssignmentId']);
        self::assertNull($regularSales[0]['emergencyAssignmentId']);
    }

    public function testSubEditorCanAssignCustomAmountWithExpiryAndNoteToEitherSalesRole(): void
    {
        $controller = new SalesController($this->database);
        $expiryDate = AppTime::now()->modify('+5 days')->format('Y-m-d');

        $assignment = $controller->assignEmergencyPackage([
            'employeeId' => 1,
            'packageAmount' => '275.50',
            'expiryDate' => $expiryDate,
            'note' => 'Short-term campaign',
        ], 6);

        $this->assertSame(1, $assignment['employeeId']);
        $this->assertSame('275.50', $assignment['packageAmount']);
        $this->assertSame(5, $assignment['daysLeft']);
        $this->assertSame($expiryDate, $assignment['expiresAt']);
        $this->assertSame('Short-term campaign', $assignment['note']);
        $listed = $controller->listEmergencySalesAssignments();
        $this->assertSame(5, $listed['nextExpiryDaysLeft']);
        $this->assertSame('team_leader', $listed['assignments'][0]['employeeRole']);
        $this->assertSame('active', $listed['assignments'][0]['status']);
        $this->assertSame('Short-term campaign', $listed['assignments'][0]['note']);
        $this->assertSame('275.50', $controller->listMyEmergencySalesAssignments(1)[0]['packageAmount']);
        $this->assertSame([], $controller->listMyEmergencySalesAssignments(2));
    }

    public function testEmergencySalesOverviewReturnsOwnSalaryAndSalesByStatus(): void
    {
        $controller = new SalesController($this->database);
        $assignment = $controller->assignEmergencyPackage([
            'employeeId' => 2,
            'packageAmount' => '350.00',
            'expiryDate' => AppTime::now()->modify('+5 days')->format('Y-m-d'),
        ], 1);
        $insert = $this->database->prepare(
            'INSERT INTO unlock_sales
                (telecaller_id, package_amount, is_employee_credited, emergency_assignment_id,
                 incentive_amount, sold_on, status, customer_reference)
             VALUES (2, :package_amount, :credited, :assignment_id, :incentive, :sold_on, :status, :customer)'
        );
        foreach ([
            ['75.00', 1, null, 'pending', 'Emergency Pending'],
            ['150.00', 1, '10.00', 'approved', 'Emergency Approved'],
            ['99.00', 0, null, 'approved', 'Non-credited Customer'],
        ] as [$amount, $credited, $incentive, $status, $customer]) {
            $insert->execute([
                'package_amount' => $amount,
                'credited' => $credited,
                'assignment_id' => $assignment['id'],
                'incentive' => $incentive,
                'sold_on' => '2025-01-12',
                'status' => $status,
                'customer' => $customer,
            ]);
        }

        $overview = $controller->myEmergencySalesOverview(2);

        $this->assertSame('30000.00', $overview['monthlySalaryInr']);
        $this->assertSame(['count' => 1, 'amount' => '75.00'], $overview['pendingSales']);
        $this->assertSame(['count' => 1, 'amount' => '150.00'], $overview['approvedSales']);
        $this->assertFalse($overview['unlockSalesUnlocked']);
        $this->assertSame('200.00', $overview['remainingEmergencySalesAmount']);
    }

    public function testEmergencySalesProgressStaysLockedWithoutAnAssignment(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE employee_salaries (user_id INTEGER PRIMARY KEY, salary_amount TEXT)');
        $database->exec('CREATE TABLE emergency_sales_assignments (
            id INTEGER PRIMARY KEY,
            employee_id INTEGER NOT NULL,
            package_amount TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $database->exec('CREATE TABLE unlock_sales (
            telecaller_id INTEGER,
            package_amount TEXT,
            emergency_assignment_id INTEGER,
            is_employee_credited INTEGER,
            status TEXT,
            customer_reference TEXT,
            rejection_reason TEXT,
            sold_on TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $database->exec("INSERT INTO employee_salaries (user_id, salary_amount) VALUES (1, '25000.00')");

        $overview = (new SalesController($database))->myEmergencySalesOverview(1);

        $this->assertSame('25000.00', $overview['monthlySalaryInr']);
        $this->assertFalse($overview['unlockSalesUnlocked']);
        $this->assertFalse($overview['canSubmitEmergencySale']);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $overview['pendingSales']);
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $overview['approvedSales']);
    }

    public function testApprovedEmergencySalesUnlockOnlyAfterTheAssignedAmountIsReached(): void
    {
        $controller = new SalesController($this->database);
        $assignment = $controller->assignEmergencyPackage([
            'employeeId' => 2,
            'packageAmount' => '150.00',
            'expiryDate' => AppTime::now()->modify('+5 days')->format('Y-m-d'),
        ], 1);
        $sale = $this->database->prepare(
            "INSERT INTO unlock_sales
                (telecaller_id, package_amount, is_employee_credited, emergency_assignment_id, status)
             VALUES (2, '150.00', 1, :assignment_id, 'approved')"
        );
        $sale->execute(['assignment_id' => $assignment['id']]);

        $progress = $controller->emergencySalesProgress(2);

        $this->assertTrue($progress['unlockSalesUnlocked']);
        $this->assertFalse($progress['canSubmitEmergencySale']);
        $this->assertSame('0.00', $progress['remainingEmergencySalesAmount']);
    }

    public function testExpiredEmergencyAssignmentRemainsSubmittableUntilApprovedTargetIsReached(): void
    {
        $this->database->exec(
            "INSERT INTO emergency_sales_assignments (employee_id, package_amount, expires_at, created_at)
             VALUES (2, '150.00', '2020-01-01', '2020-01-01 10:00:00')"
        );

        $progress = (new SalesController($this->database))->emergencySalesProgress(2);

        $this->assertSame('expired', $progress['emergencyAssignment']['status']);
        $this->assertTrue($progress['canSubmitEmergencySale']);
        $this->assertFalse($progress['unlockSalesUnlocked']);
    }

    public function testEmergencySaleSubmissionIsLinkedToItsAssignment(): void
    {
        $this->database->exec('CREATE TABLE unlock_package_incentives (package_amount TEXT PRIMARY KEY)');
        $this->database->exec("INSERT INTO unlock_package_incentives (package_amount) VALUES ('350.00')");
        $assignment = (new SalesController($this->database))->assignEmergencyPackage([
            'employeeId' => 2,
            'packageAmount' => '350.00',
            'expiryDate' => AppTime::now()->modify('+5 days')->format('Y-m-d'),
        ], 1);

        $sale = (new SalesController($this->database))->submitUnlockSale(2, [
            'customerId' => 'emergency-customer-1',
            'packageAmount' => '350.00',
            'isEmployeeCredited' => true,
        ], $assignment['id']);

        $this->assertSame($assignment['id'], $sale['emergencyAssignmentId']);
        $this->assertSame($assignment['id'], (int) $this->database->query(
            'SELECT emergency_assignment_id FROM unlock_sales WHERE id = ' . $sale['id']
        )->fetchColumn());
        $this->assertSame(['count' => 1, 'amount' => '350.00'], (new SalesController($this->database))->emergencySalesProgress(2)['pendingSales']);
    }

    public function testEmergencyPackageAssignmentRejectsInvalidExpiryAmountAndInactiveEmployees(): void
    {
        $controller = new SalesController($this->database);

        try {
            $controller->assignEmergencyPackage([
                'employeeId' => 2,
                'packageAmount' => '150',
                'expiryDate' => AppTime::now()->modify('-1 day')->format('Y-m-d'),
            ], 6);
            self::fail('Expected past expiry date to be rejected.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Choose an expiry date from today through the next 365 days.', $error->getMessage());
        }

        try {
            $controller->assignEmergencyPackage([
                'employeeId' => 2,
                'packageAmount' => '0',
                'expiryDate' => AppTime::now()->format('Y-m-d'),
            ], 6);
            self::fail('Expected zero amount to be rejected.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Enter an emergency sales amount greater than zero.', $error->getMessage());
        }

        $this->database->exec('UPDATE users SET is_active = 0 WHERE id = 2');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Choose an active Team Leader or Telecaller.');
        $controller->assignEmergencyPackage([
            'employeeId' => 2,
            'packageAmount' => '150',
            'expiryDate' => AppTime::now()->modify('+5 days')->format('Y-m-d'),
        ], 6);
    }

    public function testEmployeeCannotReceiveAnotherAssignmentUntilCurrentExpiryDateHasPassed(): void
    {
        $controller = new SalesController($this->database);
        $controller->assignEmergencyPackage([
            'employeeId' => 2,
            'packageAmount' => '150',
            'expiryDate' => AppTime::now()->modify('+2 days')->format('Y-m-d'),
        ], 6);

        try {
            $controller->assignEmergencyPackage([
                'employeeId' => 2,
                'packageAmount' => '250',
                'expiryDate' => AppTime::now()->modify('+5 days')->format('Y-m-d'),
            ], 6);
            self::fail('Expected an active assignment to block another assignment.');
        } catch (DomainException $error) {
            self::assertSame('This employee already has an active emergency sales assignment.', $error->getMessage());
        }

        $expiredDate = AppTime::now()->modify('-1 day')->format('Y-m-d');
        $expireAssignment = $this->database->prepare(
            'UPDATE emergency_sales_assignments SET expires_at = :expires_at WHERE employee_id = :employee_id'
        );
        $expireAssignment->execute(['expires_at' => $expiredDate, 'employee_id' => 2]);

        $replacement = $controller->assignEmergencyPackage([
            'employeeId' => 2,
            'packageAmount' => '250',
            'expiryDate' => AppTime::now()->modify('+5 days')->format('Y-m-d'),
        ], 6);
        $this->assertSame('250.00', $replacement['packageAmount']);
    }

    public function testSubEditorWeeklySummaryGroupsApprovedSalesByDay(): void
    {
        $today = AppTime::now()->format('Y-m-d');
        $statement = $this->database->prepare(
            "INSERT INTO unlock_sales (telecaller_id, package_amount, incentive_amount, sold_on, status, reviewed_at, customer_reference)
             VALUES (2, '125.00', '10.00', :sold_on, 'approved', :reviewed_at, 'Current Week Customer A'),
                    (2, '75.00', '5.00', :sold_on, 'approved', :reviewed_at, 'Current Week Customer B'),
                    (2, '900.00', NULL, :sold_on, 'pending', NULL, 'Pending Customer')"
        );
        $statement->execute(['sold_on' => $today, 'reviewed_at' => $today]);

        $result = (new SalesController($this->database))->subEditorSummary('this_week');

        $this->assertSame('this_week', $result['period']);
        $this->assertSame([['date' => $today, 'amount' => '200']], $result['dailySales']);
        $this->database->exec(
            "INSERT INTO unlock_sales
                (telecaller_id, package_amount, emergency_assignment_id, sold_on, status, customer_reference)
             VALUES (2, '500.00', 42, '{$today}', 'approved', 'Emergency Week Sale')"
        );
        $result = (new SalesController($this->database))->subEditorSummary('this_week');
        $this->assertSame('200', $result['totalSales']);
        $this->assertSame([['date' => $today, 'amount' => '200']], $result['dailySales']);
    }

    public function testSubEditorAchievedRewardsReturnsRecordedTeamLeaderRewards(): void
    {
        $this->database->exec(
            "INSERT INTO team_leader_target_rewards
                (team_leader_id, target_month, target_amount, achieved_sales_amount, reward_amount, granted_at)
             VALUES
                (1, '2025-01-01', '500.00', '500.00', '75.00', '2025-02-01 10:00:00')"
        );

        $rewards = (new SalesController($this->database))->subEditorAchievedRewards();

        $this->assertCount(1, $rewards);
        $this->assertSame('100001', $rewards[0]['employeeId']);
        $this->assertSame('2025-01', $rewards[0]['month']);
        $this->assertSame('75.00', $rewards[0]['rewardAmount']);
    }

    public function testSubEditorGrantedRewardsReturnsRecordedTeamLeaderIncentives(): void
    {
        $this->database->exec(
            "INSERT INTO team_grant_incentives
                (team_leader_id, target_month, team_target_amount, member_target_amount, team_member_count,
                 team_incentive_amount, leader_share_percent, leader_incentive_amount, granted_at)
             VALUES
                (1, '2025-01-01', '500.00', '250.00', 2, '30.00', '25.00', '7.50', '2025-02-01 10:00:00')"
        );

        $rewards = (new SalesController($this->database))->subEditorGrantedRewards();

        $this->assertCount(1, $rewards);
        $this->assertSame('Team Leader', $rewards[0]['leaderName']);
        $this->assertSame('2025-01', $rewards[0]['month']);
        $this->assertSame('7.50', $rewards[0]['leaderIncentiveAmount']);
    }

    public function testSubEditorPaidIncentivesCombinesApprovedSalesAndGrantedTeamRewards(): void
    {
        $this->database->exec(
            "INSERT INTO team_leader_target_rewards
                (team_leader_id, target_month, target_amount, achieved_sales_amount, reward_amount, granted_at)
             VALUES (1, '2025-01-01', '500.00', '500.00', '75.00', '2025-02-01 10:00:00')"
        );
        $this->database->exec(
            "INSERT INTO team_grant_incentives
                (team_leader_id, target_month, team_target_amount, member_target_amount, team_member_count,
                 team_incentive_amount, leader_share_percent, leader_incentive_amount, granted_at)
             VALUES (1, '2025-01-01', '500.00', '250.00', 2, '30.00', '25.00', '7.50', '2025-02-01 10:00:00')"
        );

        $report = (new SalesController($this->database))->subEditorPaidIncentives('2025-01');

        self::assertSame('2025-01', $report['month']);
        self::assertCount(3, $report['employees']);
        $employees = array_column($report['employees'], null, 'name');
        self::assertSame('82.50', $employees['Team Leader']['earnedAmount']);
        self::assertSame('30.00', $employees['Telecaller']['earnedAmount']);
        self::assertFalse($employees['Telecaller']['isPaid']);
    }

    public function testSubEditorCanMarkMonthlyIncentivePaidOnlyOnce(): void
    {
        $controller = new SalesController($this->database);

        $payout = $controller->markMonthlyIncentivePaid(2, '2025-01', 1);

        self::assertSame('200001', $payout['employeeId']);
        self::assertSame('30.00', $payout['paidAmount']);
        self::assertSame('2025-01', $payout['month']);
        self::assertNotSame('', $payout['paidAt']);

        try {
            $controller->markMonthlyIncentivePaid(2, '2025-01', 1);
            self::fail('A duplicate monthly payout should be rejected.');
        } catch (\DomainException $error) {
            self::assertSame('This employee’s incentive has already been marked paid for the month.', $error->getMessage());
        }
    }

    public function testTeamLeaderSeesOnlyAssignedTeamApprovedIncentives(): void
    {
        $result = (new SalesController($this->database))->teamIncentives(1);

        $this->assertSame('30', $result['totalIncentive']);
        $this->assertSame(2, $result['approvedSales']);
        $this->assertSame(2, $result['teamMemberCount']);
        $this->assertSame('200001', $result['employees'][0]['employeeId']);
        $this->assertSame('30', $result['employees'][0]['totalIncentive']);
        $this->assertSame(0, $result['employees'][1]['approvedSales']);
        $this->assertCount(2, $result['incentives']);
        $this->assertArrayNotHasKey('customerReference', $result['incentives'][0]);
    }

    public function testTeamIncentivesRejectNonTeamLeader(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Team incentives are only available to Team Leaders.');

        (new SalesController($this->database))->teamIncentives(2);
    }

    public function testTeamTargetRewardsShowOnlyAssignedApprovedSalesAndMemberContributions(): void
    {
        $result = (new SalesController($this->database))->teamTargetRewards(1, '2025-01');

        $this->assertSame('500.00', $result['targetAmount']);
        $this->assertSame('400.00', $result['teamSalesAmount']);
        $this->assertSame('100.00', $result['remainingAmount']);
        $this->assertSame(80.0, $result['progressPercent']);
        $this->assertFalse($result['targetAchieved']);
        $this->assertSame('400.00', $result['members'][0]['salesAmount']);
        $this->assertSame(2, $result['members'][0]['approvedSales']);
        $this->assertSame(80.0, $result['members'][0]['targetContributionPercent']);
        $this->assertSame('0.00', $result['members'][1]['salesAmount']);
        $this->assertSame([], $result['grantHistory']);
    }

    public function testTeamTargetRewardIsAutomaticallyGrantedOnceWhenAssignedTeamReachesTarget(): void
    {
        $this->database->exec("INSERT INTO unlock_sales (telecaller_id, package_amount, incentive_amount, sold_on, status, reviewed_at, customer_reference) VALUES (3, '100.00', '5.00', '2025-01-12', 'approved', '2025-01-13', 'Customer C')");
        $controller = new SalesController($this->database);

        $settings = $controller->saveTeamTargetRewardAmount('125.50', 1);
        $this->assertSame('125.50', $settings['rewardAmount']);

        $result = $controller->teamTargetRewards(1, '2025-01');
        $this->assertTrue($result['targetAchieved']);
        $this->assertSame('500.00', $result['teamSalesAmount']);
        $this->assertSame('125.50', $result['grant']['rewardAmount']);
        $this->assertSame('500.00', $result['grant']['achievedSalesAmount']);
        $this->assertCount(1, $result['grantHistory']);

        $controller->syncTeamTargetReward(1, '2025-01');
        $this->assertSame(1, (int) $this->database->query('SELECT COUNT(*) FROM team_leader_target_rewards')->fetchColumn());
    }

    public function testTeamTargetRewardIsNotGrantedWhenNoRewardAmountIsConfigured(): void
    {
        $this->database->exec("INSERT INTO unlock_sales (telecaller_id, package_amount, incentive_amount, sold_on, status, reviewed_at, customer_reference) VALUES (2, '100.00', '5.00', '2025-01-12', 'approved', '2025-01-13', 'Customer C')");

        $result = (new SalesController($this->database))->teamTargetRewards(1, '2025-01');

        $this->assertTrue($result['targetAchieved']);
        $this->assertNull($result['grant']);
        $this->assertSame([], $result['grantHistory']);
    }

    public function testTeamGrantIncentivesDivideTargetAndWaitForEveryTelecaller(): void
    {
        $result = (new SalesController($this->database))->teamGrantIncentives(1, '2025-01');

        $this->assertSame('500.00', $result['teamTargetAmount']);
        $this->assertSame('250.00', $result['memberTargetAmount']);
        $this->assertSame(2, $result['teamMemberCount']);
        $this->assertSame(1, $result['achievedMemberCount']);
        $this->assertFalse($result['allMembersReached']);
        $this->assertSame('30.00', $result['teamIncentiveAmount']);
        $this->assertSame('400.00', $result['members'][0]['salesAmount']);
        $this->assertTrue($result['members'][0]['targetReached']);
        $this->assertSame('0.00', $result['members'][1]['salesAmount']);
        $this->assertFalse($result['members'][1]['targetReached']);
        $this->assertNull($result['grant']);
    }

    public function testTeamLeaderGetsConfiguredShareAfterEveryAssignedTelecallerReachesTarget(): void
    {
        $this->database->exec("INSERT INTO unlock_sales (telecaller_id, package_amount, incentive_amount, sold_on, status, reviewed_at, customer_reference) VALUES (3, '250.00', '15.00', '2025-01-12', 'approved', '2025-01-13', 'Customer C')");
        $controller = new SalesController($this->database);

        $settings = $controller->saveTeamGrantIncentivePercentage('20', 1);
        $this->assertSame('20.00', $settings['leaderSharePercent']);

        $result = $controller->teamGrantIncentives(1, '2025-01');

        $this->assertTrue($result['allMembersReached']);
        $this->assertSame(2, $result['achievedMemberCount']);
        $this->assertSame('45.00', $result['teamIncentiveAmount']);
        $this->assertSame('20.00', $result['grant']['leaderSharePercent']);
        $this->assertSame('9.00', $result['grant']['leaderIncentiveAmount']);
        $this->assertCount(1, $result['grantHistory']);

        $controller->syncTeamGrantIncentive(1, '2025-01');
        $this->assertSame(1, (int) $this->database->query('SELECT COUNT(*) FROM team_grant_incentives')->fetchColumn());
    }

    public function testTeamGrantIncentivesRejectInvalidPercentAndNonTeamLeader(): void
    {
        $controller = new SalesController($this->database);
        try {
            $controller->saveTeamGrantIncentivePercentage('100.01', 1);
            $this->fail('A percentage greater than 100 must be rejected.');
        } catch (\InvalidArgumentException $error) {
            $this->assertSame('Enter a Team Leader incentive share between 0 and 100 percent.', $error->getMessage());
        }

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Team grant incentives are only available to Team Leaders.');
        $controller->teamGrantIncentives(2, '2025-01');
    }

    public function testEmergencySalesAreExcludedFromIncentivesRewardsAndAchievements(): void
    {
        $this->database->exec('DELETE FROM unlock_sales');
        $this->database->exec(
            "UPDATE team_leader_sales_targets
             SET target_amount = '110.00'
             WHERE team_leader_id = 1 AND target_month = '2025-01-01'"
        );
        $controller = new SalesController($this->database);
        $controller->saveTeamTargetRewardAmount('25.00', 1);
        $controller->saveTeamGrantIncentivePercentage('20.00', 1);

        $sale = $this->database->prepare(
            'INSERT INTO unlock_sales
                (telecaller_id, package_amount, incentive_amount, is_employee_credited,
                 emergency_assignment_id, sold_on, status, reviewed_at, customer_reference)
             VALUES
                (:telecaller_id, :package_amount, :incentive_amount, 1,
                 :emergency_assignment_id, \'2025-01-15\', \'approved\', \'2025-01-16\', :customer_reference)'
        );
        foreach ([
            [2, '50.00', '5.00', null, 'Regular Sale A'],
            [2, '50.00', '9.00', 42, 'Emergency Sale'],
            [3, '55.00', '5.00', null, 'Regular Sale B'],
        ] as [$telecallerId, $amount, $incentive, $emergencyAssignmentId, $reference]) {
            $sale->execute([
                'telecaller_id' => $telecallerId,
                'package_amount' => $amount,
                'incentive_amount' => $incentive,
                'emergency_assignment_id' => $emergencyAssignmentId,
                'customer_reference' => $reference,
            ]);
        }

        $employeeSales = $controller->employeeUnlockSales(2);
        $emergencySale = array_values(array_filter(
            $employeeSales['sales'],
            static fn (array $row): bool => $row['emergencyAssignmentId'] !== null
        ))[0];
        self::assertEquals(5.0, (float) $employeeSales['earnedIncentives']);
        self::assertNull($emergencySale['incentiveAmount']);
        self::assertEquals(5.0, (float) $controller->employeeIncentives(2)['totalEarned']);
        $reviewSales = $controller->listUnlockSalesForReview('approved')['sales'];
        $emergencyReviewSale = array_values(array_filter(
            $reviewSales,
            static fn (array $row): bool => $row['emergencyAssignmentId'] !== null
        ))[0];
        self::assertNull($emergencyReviewSale['incentiveAmount']);

        $teamIncentives = $controller->teamIncentives(1);
        self::assertEquals(10.0, (float) $teamIncentives['totalIncentive']);
        self::assertSame(2, $teamIncentives['approvedSales']);
        self::assertCount(2, $teamIncentives['incentives']);

        $teamRewards = $controller->teamTargetRewards(1, '2025-01');
        self::assertSame('105.00', $teamRewards['teamSalesAmount']);
        self::assertFalse($teamRewards['targetAchieved']);
        self::assertNull($teamRewards['grant']);

        $teamGrant = $controller->teamGrantIncentives(1, '2025-01');
        self::assertSame('105.00', $teamGrant['teamSalesAmount']);
        self::assertFalse($teamGrant['allMembersReached']);
        self::assertNull($teamGrant['grant']);
        self::assertCount(2, $controller->teamUnlockAchievements(1, 'team_leader')['achievements']);

        $subEditorIncentives = $controller->subEditorEarnedIncentives();
        self::assertEquals(10.0, (float) $subEditorIncentives['totalEarned']);
        self::assertSame(2, $subEditorIncentives['approvedSalesCount']);
    }

    public function testApprovingEmergencySaleDoesNotAwardPackageIncentive(): void
    {
        $this->database->exec(
            'CREATE TABLE unlock_package_incentives (
                package_amount TEXT PRIMARY KEY,
                incentive_amount TEXT NOT NULL
            )'
        );
        $this->database->exec(
            "INSERT INTO unlock_package_incentives (package_amount, incentive_amount)
             VALUES ('500.00', '25.00')"
        );
        $controller = new SalesController($this->database);
        $controller->saveTeamTargetRewardAmount('25.00', 1);
        $this->database->exec(
            "INSERT INTO unlock_sales
                (telecaller_id, package_amount, is_employee_credited, emergency_assignment_id,
                 sold_on, status, customer_reference)
             VALUES (2, '500.00', 1, 42, '2025-01-15', 'pending', 'Emergency Sale')"
        );
        $saleId = (int) $this->database->lastInsertId();

        $result = $controller->reviewUnlockSale($saleId, ['decision' => 'approved'], 1);

        self::assertNull($result['incentiveAmount']);
        self::assertSame(0, (int) $this->database->query(
            'SELECT COUNT(*) FROM team_leader_target_rewards'
        )->fetchColumn());
        self::assertEquals(30.0, (float) $controller->employeeIncentives(2)['totalEarned']);
    }

    public function testSubmitUnlockSaleStoresTheVerifiedCustomerAndActivatedPackageForReview(): void
    {
        $this->database->exec('CREATE TABLE unlock_package_incentives (package_amount TEXT PRIMARY KEY)');
        $this->database->exec("INSERT INTO unlock_package_incentives (package_amount) VALUES ('350.00')");

        $sale = (new SalesController($this->database))->submitUnlockSale(2, [
            'customerId' => 'arowin-10001',
            'packageAmount' => '350.00',
            'isEmployeeCredited' => true,
        ]);

        $this->assertSame('arowin-10001', $sale['customerReference']);
        $this->assertSame('350.00', $sale['packageAmount']);
        $this->assertSame('pending', $sale['status']);
        $findSale = $this->database->prepare('SELECT customer_reference FROM unlock_sales WHERE id = :id');
        $findSale->execute(['id' => $sale['id']]);
        $this->assertSame('arowin-10001', $findSale->fetchColumn());
    }

    public function testSubmitUnlockSaleRejectsUnsupportedPackage(): void
    {
        $controller = new SalesController($this->database);

        $this->expectException(\InvalidArgumentException::class);
        $controller->submitUnlockSale(2, [
            'customerId' => 'arowin-10001',
            'packageAmount' => '999.00',
        ]);
    }
}
