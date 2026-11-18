<?php

declare(strict_types=1);

use Arowin\Modules\Employees\EmployeeController;
use PHPUnit\Framework\TestCase;

final class EmployeeControllerTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        putenv('AROWIN_CREDENTIAL_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT UNIQUE,
            name TEXT,
            email TEXT UNIQUE,
            password_hash TEXT,
            role TEXT,
            mobile TEXT,
            date_of_birth TEXT,
            address TEXT,
            profile_image_path TEXT,
            is_active INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->exec('CREATE TABLE team_leader_leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT UNIQUE
        )');
        $this->database->exec('CREATE TABLE employee_salaries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            joining_date TEXT,
            salary_amount REAL
        )');
        $this->database->exec('CREATE TABLE credential_downloads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            token_hash TEXT,
            encrypted_password TEXT,
            expires_at TEXT,
            redeemed_at TEXT,
            email_sent_at TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->exec('CREATE TABLE activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT,
            metadata TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');

        $this->database->exec("INSERT INTO users (id, employee_id, name, email, password_hash, role, mobile, date_of_birth, address, profile_image_path, is_active) VALUES (1, '100001', 'Creator', 'creator@example.com', 'hash', 'team_leader', '9000000001', '1990-05-01', 'Chennai', NULL, 1)");
    }

    public function testCreateEmployeeCreatesUserAndReturnsSummary(): void
    {
        $controller = new EmployeeController($this->database);

        $result = $controller->createEmployee([
            'fullName' => 'New Employee',
            'email' => 'new.employee@example.com',
            'password' => 'StrongPass1',
            'mobile' => '9000000099',
            'dob' => '1995-08-12',
            'address' => 'Hyderabad',
            'role' => 'telecaller',
        ], 1);

        $this->assertSame('New Employee', $result['employee']['fullName']);
        $this->assertSame('new.employee@example.com', $result['employee']['email']);
        $this->assertSame('Active', $result['employee']['status']);
        $this->assertSame('telecaller', $result['employee']['dashboard']);
        $this->assertArrayHasKey('emailDelivery', $result);
    }

    public function testCreateEmployeeRejectsEmptyRequiredFields(): void
    {
        $controller = new EmployeeController($this->database);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All employee fields and a valid role are required.');

        $controller->createEmployee([
            'fullName' => '',
            'email' => '',
            'password' => '',
            'mobile' => '',
            'dob' => '',
            'address' => '',
            'role' => '',
        ], 1);
    }

    public function testDuplicateEmailIsRejectedDuringEmployeeCreation(): void
    {
        $this->database->exec("INSERT INTO users (employee_id, name, email, password_hash, role, mobile, date_of_birth, address, profile_image_path, is_active) VALUES ('100002', 'Existing Employee', 'duplicate@example.com', 'hash', 'telecaller', '9000000100', '1993-02-02', 'Vijayawada', NULL, 1)");
        $controller = new EmployeeController($this->database);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('An account with this email already exists.');

        $controller->createEmployee([
            'fullName' => 'Another Person',
            'email' => 'duplicate@example.com',
            'password' => 'StrongPass1',
            'mobile' => '9000000111',
            'dob' => '1988-11-04',
            'address' => 'Kurnool',
            'role' => 'telecaller',
        ], 1);
    }

    public function testSubEditorCanResetTeamLeaderPasswordWithoutCurrentPassword(): void
    {
        $this->database->exec("INSERT INTO users (id, employee_id, name, email, password_hash, role, mobile, date_of_birth, address, profile_image_path, is_active) VALUES (2, '100002', 'Sub Editor', 'editor@example.com', 'hash', 'sub_editor', '9000000002', '1990-01-01', 'Chennai', NULL, 1)");

        (new EmployeeController($this->database))->resetEmployeePassword(1, [
            'newPassword' => 'NewLeaderPass123',
            'confirmPassword' => 'NewLeaderPass123',
        ], 2);

        $passwordHash = $this->database->query('SELECT password_hash FROM users WHERE id = 1')->fetchColumn();
        $this->assertIsString($passwordHash);
        $this->assertTrue(password_verify('NewLeaderPass123', $passwordHash));

        $activity = $this->database->query(
            "SELECT user_id, action, metadata FROM activity_logs ORDER BY id DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(2, (int) $activity['user_id']);
        $this->assertSame('employee.password_reset', $activity['action']);
        $this->assertStringNotContainsString('NewLeaderPass123', $activity['metadata']);
    }

    public function testSubEditorCanResetTelecallerPasswordWithoutCurrentPassword(): void
    {
        $this->database->exec("INSERT INTO users (id, employee_id, name, email, password_hash, role, mobile, date_of_birth, address, profile_image_path, is_active) VALUES (2, '100002', 'Telecaller', 'caller@example.com', 'hash', 'telecaller', '9000000002', '1990-01-01', 'Chennai', NULL, 1)");

        (new EmployeeController($this->database))->resetEmployeePassword(2, [
            'newPassword' => 'NewCallerPass123',
            'confirmPassword' => 'NewCallerPass123',
        ], 1);

        $passwordHash = $this->database->query('SELECT password_hash FROM users WHERE id = 2')->fetchColumn();
        $this->assertIsString($passwordHash);
        $this->assertTrue(password_verify('NewCallerPass123', $passwordHash));
    }

    public function testEmployeePasswordResetRejectsOtherEmployeeRoles(): void
    {
        $this->database->exec("INSERT INTO users (id, employee_id, name, email, password_hash, role, mobile, date_of_birth, address, profile_image_path, is_active) VALUES (2, '100002', 'Sub Editor', 'editor@example.com', 'hash', 'sub_editor', '9000000002', '1990-01-01', 'Chennai', NULL, 1)");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected account is not a Team Leader or Telecaller.');

        (new EmployeeController($this->database))->resetEmployeePassword(2, [
            'newPassword' => 'NewLeaderPass123',
            'confirmPassword' => 'NewLeaderPass123',
        ], 1);
    }

    public function testTeamLeaderPasswordResetRejectsMismatchedPasswords(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The new passwords do not match.');

        (new EmployeeController($this->database))->resetEmployeePassword(1, [
            'newPassword' => 'NewLeaderPass123',
            'confirmPassword' => 'DifferentPass123',
        ], 1);
    }
}
