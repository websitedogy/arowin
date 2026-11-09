<?php

declare(strict_types=1);

use Arowin\RouteAuthorization;
use PHPUnit\Framework\TestCase;

final class RouteAuthorizationTest extends TestCase
{
    public function testAuthenticatedSessionsPass(): void
    {
        $session = ['user' => ['id' => 14, 'role' => 'team_leader']];

        $this->assertNull(RouteAuthorization::ensureAuthenticated($session));
        $this->assertNull(RouteAuthorization::ensureKycEmployee($session));
        $this->assertNull(RouteAuthorization::ensureKycEmployee(['user' => ['id' => 5, 'role' => 'sub_editor']]));
    }

    public function testUnauthenticatedSessionsFail(): void
    {
        $this->assertSame('Sign in to continue.', RouteAuthorization::ensureAuthenticated([]));
    }

    public function testRoleRestrictionsRejectUnauthorizedAccess(): void
    {
        $unauthorized = ['user' => ['id' => 9, 'role' => 'support_user']];

        $this->assertSame('Only Sub Editors can manage employee accounts.', RouteAuthorization::ensureSubEditor($unauthorized));
        $this->assertSame('Only Team Leaders and Telecallers can submit unlock sales.', RouteAuthorization::ensureSalesEmployee($unauthorized));
        $this->assertNull(RouteAuthorization::ensureSalesEmployee(['user' => ['id' => 3, 'role' => 'telecaller']]));
    }
}
