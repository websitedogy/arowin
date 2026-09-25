<?php
/**
 * Centralized PDO connection (reads from .env via env()).
 */
declare(strict_types=1);

/**
 * @return PDO
 */
function arowin_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) env('DB_HOST', '127.0.0.1');
    $port = (string) env('DB_PORT', '3306');
    $name = (string) env('DB_DATABASE', 'arowin');
    $user = (string) env('DB_USERNAME', 'root');
    $pass = (string) env('DB_PASSWORD', '');
    $charset = (string) env('DB_CHARSET', 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Return TIMESTAMP values as UTC; PHP formats them to IST for display.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}, time_zone = '+00:00'",
        ]);
    } catch (PDOException $e) {
        if (env('APP_DEBUG', false)) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
        throw new RuntimeException('Database connection failed. Please try again later.', 0, $e);
    }

    return $pdo;
}

return [
    'connection' => env('DB_CONNECTION', 'mysql'),
    'host'       => env('DB_HOST', '127.0.0.1'),
    'port'       => env('DB_PORT', '3306'),
    'database'   => env('DB_DATABASE', 'arowin'),
    'username'   => env('DB_USERNAME', 'root'),
    'password'   => env('DB_PASSWORD', ''),
    'charset'    => env('DB_CHARSET', 'utf8mb4'),
    'collation'  => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
];
