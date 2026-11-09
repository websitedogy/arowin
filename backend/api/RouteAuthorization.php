<?php

declare(strict_types=1);

namespace Arowin;

final class RouteAuthorization
{
    public static function isAuthenticated(array $session): bool
    {
        return isset($session['user']['id']);
    }

    public static function ensureAuthenticated(array $session): ?string
    {
        return self::isAuthenticated($session) ? null : 'Sign in to continue.';
    }

    public static function ensureRole(array $session, array $allowedRoles, string $deniedMessage): ?string
    {
        if (!self::isAuthenticated($session)) {
            return self::ensureAuthenticated($session);
        }

        $role = (string) ($session['user']['role'] ?? '');
        return in_array($role, $allowedRoles, true) ? null : $deniedMessage;
    }

    public static function ensureSubEditor(array $session): ?string
    {
        return self::ensureRole($session, ['sub_editor'], 'Only Sub Editors can manage employee accounts.');
    }

    public static function ensureAttendanceEmployee(array $session): ?string
    {
        return self::ensureRole($session, ['team_leader', 'telecaller'], 'Only Team Leaders and Telecallers can mark attendance.');
    }

    public static function ensureLeaveApplicant(array $session): ?string
    {
        return self::ensureRole($session, ['team_leader', 'telecaller'], 'Only Team Leaders and Telecallers can apply for leave.');
    }

    public static function ensureKycEmployee(array $session): ?string
    {
        return self::ensureRole(
            $session,
            ['sub_editor', 'team_leader', 'telecaller'],
            'Employee KYC is available to Sub Editors, Team Leaders, and Telecallers only.'
        );
    }

    public static function ensureDailyActivityEmployee(array $session): ?string
    {
        return self::ensureRole($session, ['team_leader', 'telecaller'], 'Only Team Leaders and Telecallers can submit daily activities.');
    }

    public static function ensureSalesEmployee(array $session): ?string
    {
        return self::ensureRole($session, ['team_leader', 'telecaller'], 'Only Team Leaders and Telecallers can submit unlock sales.');
    }

    public static function ensureSupportEmployee(array $session): ?string
    {
        return self::ensureRole($session, ['team_leader', 'telecaller'], 'Only Team Leaders and Telecallers can raise support tickets.');
    }
}
