<?php

declare(strict_types=1);

namespace Arowin\Modules\Dashboard;

use Arowin\EmployeeRoles;
use PDO;

final class DashboardController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function summary(string $role): array
    {
        $statement = $this->database->query(
            'SELECT role, COUNT(*) AS total
             FROM users
             WHERE is_active = 1
             GROUP BY role'
        );
        $counts = array_fill_keys(array_keys(EmployeeRoles::LABELS), 0);
        foreach ($statement->fetchAll() as $row) {
            $counts[$row['role']] = (int) $row['total'];
        }

        return [
            'success' => true,
            'dashboard' => [
                'module' => $role,
                'kpis' => [
                    'employees' => array_sum($counts),
                    'sub_editors' => $counts['sub_editor'],
                    'team_leaders' => $counts['team_leader'],
                    'telecallers' => $counts['telecaller'],
                ],
            ],
        ];
    }
}
