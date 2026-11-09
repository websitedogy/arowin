<?php

declare(strict_types=1);

$environmentFile = __DIR__ . '/../.env';
if (is_file($environmentFile)) {
    $environment = parse_ini_file($environmentFile, false, INI_SCANNER_RAW);
    if ($environment === false) {
        throw new RuntimeException('Backend environment file could not be read.');
    }

    foreach ($environment as $name => $value) {
        $existingValue = getenv($name);
        if ($existingValue === false || $existingValue === '') {
            putenv($name . '=' . $value);
        }
    }
}

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $className): void {
    $prefix = 'Arowin\\';
    if (strncmp($className, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $segments = explode('\\', $relativeClass);
    if (($segments[0] ?? null) === 'Modules') {
        $segments[0] = 'modules';
        if (isset($segments[1])) {
            $moduleDirectories = [
                'DailyActivities' => 'daily-activities',
                'TeamAssignments' => 'team-assignments',
            ];
            $segments[1] = $moduleDirectories[$segments[1]] ?? strtolower($segments[1]);
        }
    }
    $filePath = __DIR__ . '/' . implode('/', $segments) . '.php';

    if (is_file($filePath)) {
        require $filePath;
    }
});

function loadConfig(string $name): array
{
    $path = __DIR__ . '/config/' . $name . '.php';
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Config file not found: %s', $name));
    }

    return require $path;
}

function jsonResponse(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
        header('X-Arowin-Session: ' . session_id());
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function parseJsonBody(): array
{
    $input = file_get_contents('php://input');
    if ($input === false || trim($input) === '') {
        return [];
    }

    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : [];
}
