<?php

declare(strict_types=1);

use Arowin\Modules\Auth\AuthController;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT,
            name TEXT,
            email TEXT,
            password_hash TEXT,
            role TEXT,
            mobile TEXT,
            date_of_birth TEXT,
            address TEXT,
            profile_image_path TEXT,
            is_active INTEGER
        )');
        $this->database->exec('CREATE TABLE employee_salaries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            joining_date TEXT
        )');

        $this->database->exec(
            "INSERT INTO users (employee_id, name, email, password_hash, role, mobile, date_of_birth, address, profile_image_path, is_active)
             VALUES ('123456', 'Team Leader One', 'leader@example.com', '" . password_hash('Pass1234', PASSWORD_DEFAULT) . "', 'team_leader', '9000000001', '1990-04-01', 'Chennai', NULL, 1)"
        );
        $this->database->exec("INSERT INTO employee_salaries (user_id, joining_date) VALUES (1, '2024-01-15')");
    }

    public function testLoginWithEmployeeIdSucceeds(): void
    {
        $controller = new AuthController($this->database);

        $result = $controller->login(['username' => '123456', 'password' => 'Pass1234']);

        $this->assertTrue($result['success']);
        $this->assertSame('team_leader', $result['user']['role']);
        $this->assertSame('123456', $result['user']['employeeId']);
        $this->assertSame('leader@example.com', $result['user']['email']);
    }

    public function testLoginWithEmailFailsForWrongPassword(): void
    {
        $controller = new AuthController($this->database);

        $result = $controller->login(['email' => 'leader@example.com', 'password' => 'WrongPass']);

        $this->assertFalse($result['success']);
        $this->assertSame('Invalid email or Employee ID and password.', $result['message']);
    }

    public function testCurrentUserReturnsNormalizedEmployeeData(): void
    {
        $controller = new AuthController($this->database);

        $user = $controller->currentUser(1);

        $this->assertNotNull($user);
        $this->assertSame('123456', $user['employeeId']);
        $this->assertSame('2024-01-15', $user['joiningDate']);
    }
}
