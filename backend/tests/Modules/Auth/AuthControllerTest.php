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

    public function testChangePasswordVerifiesCurrentPasswordAndStoresNewHash(): void
    {
        $controller = new AuthController($this->database);

        $controller->changePassword(1, [
            'currentPassword' => 'Pass1234',
            'newPassword' => 'UpdatedPass123',
            'confirmPassword' => 'UpdatedPass123',
        ]);

        $passwordHash = $this->database->query('SELECT password_hash FROM users WHERE id = 1')->fetchColumn();
        $this->assertIsString($passwordHash);
        $this->assertTrue(password_verify('UpdatedPass123', $passwordHash));
        $this->assertFalse(password_verify('Pass1234', $passwordHash));
        $this->assertTrue($controller->login(['username' => '123456', 'password' => 'UpdatedPass123'])['success']);
    }

    public function testChangePasswordRejectsAnIncorrectCurrentPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Your current password is incorrect.');

        (new AuthController($this->database))->changePassword(1, [
            'currentPassword' => 'WrongPass',
            'newPassword' => 'UpdatedPass123',
            'confirmPassword' => 'UpdatedPass123',
        ]);
    }

    public function testChangePasswordRejectsMismatchedNewPasswords(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The new passwords do not match.');

        (new AuthController($this->database))->changePassword(1, [
            'currentPassword' => 'Pass1234',
            'newPassword' => 'UpdatedPass123',
            'confirmPassword' => 'DifferentPass123',
        ]);
    }

    public function testChangePasswordRequiresAtLeastEightCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Your new password must be between 8 and 72 characters.');

        (new AuthController($this->database))->changePassword(1, [
            'currentPassword' => 'Pass1234',
            'newPassword' => 'short',
            'confirmPassword' => 'short',
        ]);
    }
}
