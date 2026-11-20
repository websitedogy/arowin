<?php

declare(strict_types=1);

use Arowin\EnvironmentFrontendUrlProvider;
use PHPUnit\Framework\TestCase;

final class EnvironmentFrontendUrlProviderTest extends TestCase
{
    private array $environmentVariables = [
        'AROWIN_PUBLIC_FRONTEND_URL',
        'AROWIN_PUBLIC_FRONTEND_URL_SUB_EDITOR',
        'AROWIN_PUBLIC_FRONTEND_URL_TELECALLER',
        'AROWIN_PUBLIC_FRONTEND_URL_TEAM_LEADER',
    ];

    private array $previousValues = [];

    protected function setUp(): void
    {
        foreach ($this->environmentVariables as $name) {
            $this->previousValues[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->previousValues as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
    }

    public function testRoleUrlsUseTheProductionDomains(): void
    {
        $provider = new EnvironmentFrontendUrlProvider();

        $this->assertSame('https://subeditor.arowinglobal.com', $provider->urlForRole('Sub Editor'));
        $this->assertSame('https://telecaller.arowinglobal.com', $provider->urlForRole('telecaller'));
        $this->assertSame('https://teamlead.arowinglobal.com', $provider->urlForRole('Team Leader'));
    }

    public function testRoleUrlCanBeOverriddenForTheDeployment(): void
    {
        putenv('AROWIN_PUBLIC_FRONTEND_URL_TELECALLER=https://preview.example.test');

        $this->assertSame(
            'https://preview.example.test',
            (new EnvironmentFrontendUrlProvider())->urlForRole('Telecaller')
        );
    }

    public function testUnknownRolesAreRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A supported employee role is required');

        (new EnvironmentFrontendUrlProvider())->urlForRole('administrator');
    }
}
