<?php

declare(strict_types=1);

namespace Arowin;

use PDO;
use PDOException;
use RuntimeException;

final class GlobalCustomerDatabase
{
    public static function connect(): PDO
    {
        $config = \loadConfig('arowin_global_database');

        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('The PHP pdo_mysql extension is required to connect to the Arowin Global database.');
        }
        if ($config['database'] === '' || $config['username'] === '') {
            throw new RuntimeException('Configure AROWIN_GLOBAL_DB_NAME and AROWIN_GLOBAL_DB_USER in backend/.env.');
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
                'Could not connect to the Arowin Global database. Check AROWIN_GLOBAL_DB_* settings.',
                0,
                $error
            );
        }
    }
}
