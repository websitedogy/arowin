<?php

declare(strict_types=1);

namespace Arowin;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    public static function connect(): PDO
    {
        $config = \loadConfig('database');

        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('The PHP pdo_mysql extension is required to connect to MySQL.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            return new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $error) {
            throw new RuntimeException(
                'Could not connect to MySQL. Check the database service and DB_* settings.',
                0,
                $error
            );
        }
    }
}
