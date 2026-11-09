<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

use PDO;

final class PdoUserRepository implements UserRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function findSalesEmployeeById(int $userId): ?array
    {
        $statement = $this->database->prepare(
            "SELECT name, email FROM users
             WHERE id = :id AND role IN ('telecaller', 'team_leader') AND is_active = 1
             LIMIT 1"
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user) || !filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
        ];
    }
}
