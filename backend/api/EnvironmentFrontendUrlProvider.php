<?php

declare(strict_types=1);

namespace Arowin;

final class EnvironmentFrontendUrlProvider implements FrontendUrlProvider
{
    public function url(): string
    {
        $frontendUrl = rtrim(getenv('AROWIN_PUBLIC_FRONTEND_URL') ?: 'http://localhost:5173', '/');
        $urlParts = parse_url($frontendUrl);
        $localHosts = ['localhost', '127.0.0.1', '::1'];

        if (!is_array($urlParts)
            || !isset($urlParts['scheme'], $urlParts['host'])
            || !in_array(strtolower((string) $urlParts['scheme']), ['http', 'https'], true)
            || (strtolower((string) $urlParts['scheme']) !== 'https' && !in_array(strtolower((string) $urlParts['host']), $localHosts, true))
            || isset($urlParts['user'])
            || isset($urlParts['pass'])) {
            throw new \RuntimeException('AROWIN_PUBLIC_FRONTEND_URL must use HTTPS except for localhost development.');
        }

        return $frontendUrl;
    }
}
