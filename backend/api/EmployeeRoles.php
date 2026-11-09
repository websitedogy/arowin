<?php

declare(strict_types=1);

namespace Arowin;

final class EmployeeRoles
{
    public const LABELS = [
        'sub_editor' => 'Sub Editor',
        'team_leader' => 'Team Leader',
        'telecaller' => 'Telecaller',
    ];

    public static function label(string $role): string
    {
        return self::LABELS[$role] ?? $role;
    }

    public static function normalize(string $role): ?string
    {
        $normalized = strtolower(trim(str_replace(' ', '_', $role)));

        return array_key_exists($normalized, self::LABELS) ? $normalized : null;
    }
}
