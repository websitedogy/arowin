<?php

declare(strict_types=1);

use Arowin\Modules\Leave\LeaveController;
use PHPUnit\Framework\TestCase;

final class LeaveControllerTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new LeaveCompatPdo();
        $this->database->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT,
            name TEXT,
            role TEXT,
            is_active INTEGER,
            email TEXT,
            password_hash TEXT
        )');
        $this->database->exec('CREATE TABLE leave_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            leave_type TEXT,
            start_date TEXT,
            end_date TEXT,
            reason TEXT,
            status TEXT DEFAULT "pending",
            reviewed_by INTEGER,
            reviewed_at TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');

        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (1, '100001', 'Employee One', 'telecaller', 1, 'employee@example.com', 'hash')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (2, '100002', 'Reviewer One', 'team_leader', 1, 'reviewer@example.com', 'hash')");
    }

    public function testApplyLeaveCreatesPendingRequest(): void
    {
        $controller = new LeaveController($this->database);
        $startDate = date('Y-m-d', strtotime('+2 days'));
        $endDate = date('Y-m-d', strtotime('+4 days'));

        $result = $controller->apply(1, [
            'leaveType' => 'casual',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reason' => 'Family function',
        ]);

        $this->assertSame('pending', $result['status']);
        $this->assertSame($startDate, $result['startDate']);
        $this->assertSame($endDate, $result['endDate']);
    }

    public function testOverlappingLeaveRequestIsRejected(): void
    {
        $controller = new LeaveController($this->database);
        $startDate = date('Y-m-d', strtotime('+5 days'));
        $endDate = date('Y-m-d', strtotime('+7 days'));

        $controller->apply(1, [
            'leaveType' => 'sick',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reason' => 'Medical rest',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('You already have a pending or approved leave request for these dates.');

        $controller->apply(1, [
            'leaveType' => 'casual',
            'startDate' => $startDate,
            'endDate' => date('Y-m-d', strtotime('+6 days')),
            'reason' => 'Duplicate request',
        ]);
    }

    public function testDecideApprovesPendingLeaveRequest(): void
    {
        $controller = new LeaveController($this->database);
        $startDate = date('Y-m-d', strtotime('+9 days'));
        $result = $controller->apply(1, [
            'leaveType' => 'personal',
            'startDate' => $startDate,
            'endDate' => date('Y-m-d', strtotime('+10 days')),
            'reason' => 'Travel',
        ]);

        $controller->decide((int) $result['id'], 2, 'approved');

        $status = $this->database->query('SELECT status FROM leave_requests WHERE id = ' . (int) $result['id'])->fetchColumn();
        $this->assertSame('approved', $status);
    }
}

final class LeaveCompatPdo extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function prepare(string $query, array $driverOptions = []): PDOStatement
    {
        $query = preg_replace('/\s+FOR UPDATE\b/i', '', $query);

        return parent::prepare($query, $driverOptions);
    }
}
