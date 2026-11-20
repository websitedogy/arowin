<?php

declare(strict_types=1);

namespace Arowin;

final class EnvironmentFrontendUrlProvider implements FrontendUrlProvider
{
    private const ROLE_URLS = [
        'sub_editor' => 'https://subeditor.arowinglobal.com',
        'telecaller' => 'https://telecaller.arowinglobal.com',
        'team_leader' => 'https://teamlead.arowinglobal.com',
    ];

    public function url(): string
    {
        return $this->validatedUrl(
            getenv('AROWIN_PUBLIC_FRONTEND_URL') ?: 'http://localhost:5173',
            'AROWIN_PUBLIC_FRONTEND_URL'
        );
    }

    public function urlForRole(string $role): string
    {
        $roleKey = EmployeeRoles::normalize($role);
        if ($roleKey === null) {
            throw new \RuntimeException('A supported employee role is required to build the frontend URL.');
        }

        $environmentVariable = 'AROWIN_PUBLIC_FRONTEND_URL_' . strtoupper($roleKey);
        $frontendUrl = getenv($environmentVariable) ?: self::ROLE_URLS[$roleKey];

        return $this->validatedUrl($frontendUrl, $environmentVariable);
    }

    private function validatedUrl(string $frontendUrl, string $environmentVariable): string
    {
        $frontendUrl = rtrim($frontendUrl, '/');
        $urlParts = parse_url($frontendUrl);
        $localHosts = ['localhost', '127.0.0.1', '::1'];

        if (!is_array($urlParts)
            || !isset($urlParts['scheme'], $urlParts['host'])
            || !in_array(strtolower((string) $urlParts['scheme']), ['http', 'https'], true)
            || (strtolower((string) $urlParts['scheme']) !== 'https' && !in_array(strtolower((string) $urlParts['host']), $localHosts, true))
            || isset($urlParts['user'])
            || isset($urlParts['pass'])) {
            throw new \RuntimeException($environmentVariable . ' must use HTTPS except for localhost development.');
        }

        return $frontendUrl;
    }
}
