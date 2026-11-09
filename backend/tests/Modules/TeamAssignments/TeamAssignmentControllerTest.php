<?php

declare(strict_types=1);

use Arowin\Modules\TeamAssignments\TeamAssignmentController;
use PHPUnit\Framework\TestCase;

final class TeamAssignmentControllerTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new TeamAssignmentCompatPdo();
        $this->database->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT,
            name TEXT,
            email TEXT,
            role TEXT,
            mobile TEXT,
            joining_date TEXT,
            is_active INTEGER,
            created_at TEXT
        )');
        $this->database->exec('CREATE TABLE employee_salaries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            joining_date TEXT
        )');
        $this->database->exec('CREATE TABLE team_assignments (
            team_leader_id INTEGER,
            telecaller_id INTEGER,
            assigned_by INTEGER,
            assigned_at TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (team_leader_id, telecaller_id)
        )');
        $this->database->exec('CREATE TABLE activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT,
            metadata TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )');

        $this->database->exec("INSERT INTO users (id, employee_id, name, email, role, mobile, joining_date, is_active, created_at) VALUES (1, '100001', 'Team Leader', 'leader@example.com', 'team_leader', '9000000001', '2024-01-01', 1, '2024-01-01 00:00:00')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, email, role, mobile, joining_date, is_active, created_at) VALUES (2, '200001', 'Telecaller One', 'telecaller1@example.com', 'telecaller', '9000000002', '2024-02-01', 1, '2024-02-01 00:00:00')");
        $this->database->exec("INSERT INTO users (id, employee_id, name, email, role, mobile, joining_date, is_active, created_at) VALUES (3, '300001', 'Sub Editor', 'editor@example.com', 'sub_editor', '9000000003', '2024-03-01', 1, '2024-03-01 00:00:00')");
        $this->database->exec("INSERT INTO employee_salaries (user_id, joining_date) VALUES (1, '2024-01-01')");
        $this->database->exec("INSERT INTO employee_salaries (user_id, joining_date) VALUES (2, '2024-02-01')");
    }

    public function testListAvailableTelecallersReturnsOnlyUnassignedActiveEmployees(): void
    {
        $controller = new TeamAssignmentController($this->database);

        $result = $controller->listAvailableTelecallers(1);

        $this->assertCount(1, $result);
        $this->assertSame('Telecaller One', $result[0]['fullName']);
        $this->assertSame('200001', $result[0]['employeeId']);
    }

    public function testAssignTelecallerCreatesAssignmentAndRejectsDuplicate(): void
    {
        $controller = new TeamAssignmentController($this->database);

        $controller->assignTelecaller(1, 2, 3);
        $assigned = $controller->listAssignedTelecallers(1);

        $this->assertCount(1, $assigned);
        $this->assertSame('Telecaller One', $assigned[0]['fullName']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This Telecaller has already been assigned to a Team Leader.');

        $controller->assignTelecaller(1, 2, 3);
    }

    public function testListAssignedTelecallersRejectsUnknownLeader(): void
    {
        $controller = new TeamAssignmentController($this->database);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected Team Leader does not exist.');

        $controller->listAssignedTelecallers(999);
    }
}

final class TeamAssignmentCompatPdo extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $this->exec('CREATE TABLE information_schema.COLUMNS (
            TABLE_SCHEMA TEXT,
            TABLE_NAME TEXT,
            COLUMN_NAME TEXT
        )');
        $this->exec("INSERT INTO information_schema.COLUMNS (TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME) VALUES ('main', 'users', 'joining_date')");
    }

    public function prepare(string $query, array $driverOptions = []): PDOStatement
    {
        $query = preg_replace('/\s+FOR UPDATE\b/i', '', $query);
        $query = str_replace('DATABASE()', "'main'", $query);

        return parent::prepare($query, $driverOptions);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$params): PDOStatement|false
    {
        $query = preg_replace('/\s+FOR UPDATE\b/i', '', $query);
        $query = str_replace('DATABASE()', "'main'", $query);

        return parent::query($query, $fetchMode, ...$params);
    }
}
