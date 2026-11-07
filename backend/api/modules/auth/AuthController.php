<?php

declare(strict_types=1);

namespace Arowin\Modules\Auth;

use PDO;

final class AuthController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function currentUser(int $userId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, employee_id, name, email, role
             FROM users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!is_array($user)) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'employeeId' => (string) $user['employee_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }

    public function login(array $payload): array
    {
        $identifier = trim((string) ($payload['username'] ?? $payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($identifier === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Email or Employee ID and password are required.',
            ];
        }

        $isEmployeeId = preg_match('/^[0-9]{6}$/', $identifier) === 1;
        $statement = $this->database->prepare(
            $isEmployeeId
                ? "SELECT id, employee_id, name, email, password_hash, role
                   FROM users
                   WHERE employee_id = :identifier
                     AND role IN ('team_leader', 'telecaller')
                     AND is_active = 1
                   LIMIT 1"
                : 'SELECT id, employee_id, name, email, password_hash, role
                   FROM users
                   WHERE email = :identifier AND is_active = 1
                   LIMIT 1'
        );
        $statement->execute(['identifier' => $isEmployeeId ? $identifier : strtolower($identifier)]);
        $user = $statement->fetch();

        if (!is_array($user) || !password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid email or Employee ID and password.',
            ];
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'employeeId' => (string) $user['employee_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user' => $_SESSION['user'],
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
