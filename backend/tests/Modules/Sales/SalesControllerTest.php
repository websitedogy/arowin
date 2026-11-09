<?php

declare(strict_types=1);

use Arowin\Modules\Sales\SalesController;
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
            incentive_amount TEXT,
            sold_on TEXT,
            status TEXT,
            reviewed_at TEXT,
            customer_reference TEXT
        )');

        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (1, '100001', 'Team Leader', 'team_leader', 1, 'leader@example.com', 'hash')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (2, '200001', 'Telecaller', 'telecaller', 1, 'telecaller@example.com', 'hash')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (3, '200002', 'Second Telecaller', 'telecaller', 1, 'second@example.com', 'hash')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (4, '100002', 'Other Team Leader', 'team_leader', 1, 'other@example.com', 'hash')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (5, '200003', 'Other Team Telecaller', 'telecaller', 1, 'other-caller@example.com', 'hash')");
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
        $controller = new SalesController($this->database);

        $result = $controller->subEditorSummary('total');

        $this->assertSame('total', $result['period']);
        $this->assertSame('550', $result['totalSales']);
        $this->assertSame(2, $result['salespersonCount']);
        $this->assertSame(3, $result['salesEntries']);
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
}
