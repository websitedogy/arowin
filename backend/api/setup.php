<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = loadConfig('database');
$databaseName = (string) $config['database'];
$schemaOnly = in_array('--schema-only', $argv, true);
$adminEmail = strtolower(trim((string) (getenv('AROWIN_INITIAL_ADMIN_EMAIL') ?: '')));
$adminPassword = (string) (getenv('AROWIN_INITIAL_ADMIN_PASSWORD') ?: '');
$adminName = trim((string) (getenv('AROWIN_INITIAL_ADMIN_NAME') ?: 'Sub Editor Admin'));

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "The PHP pdo_mysql extension is required.\n");
    exit(1);
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
    fwrite(STDERR, "DB_NAME may contain only letters, numbers, and underscores.\n");
    exit(1);
}

if (!$schemaOnly && (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPassword) < 8)) {
    fwrite(STDERR, "Set AROWIN_INITIAL_ADMIN_EMAIL and an AROWIN_INITIAL_ADMIN_PASSWORD of at least 8 characters.\n");
    exit(1);
}

try {
    $connection = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=%s', $config['host'], $config['port'], $config['charset']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $connection->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $databaseName));
    $connection->exec(sprintf('USE `%s`', $databaseName));

    if ($schemaOnly) {
        $usersTable = $connection->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"
        );
        if ((int) $usersTable->fetchColumn() === 0) {
            throw new RuntimeException('Schema-only setup requires an existing active Sub Editor account.');
        }

        $activeAdmin = $connection->query(
            "SELECT COUNT(*) FROM users WHERE role = 'sub_editor' AND is_active = 1"
        );
        if ((int) $activeAdmin->fetchColumn() === 0) {
            throw new RuntimeException('Schema-only setup requires an existing active Sub Editor account.');
        }
    }

    $schemaPath = __DIR__ . '/../db/schema.sql';
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('Database schema file could not be read.');
    }
    $statements = preg_split('/;\s*(?:\R|$)/', trim($schema));
    if ($statements === false) {
        throw new RuntimeException('Database schema statements could not be parsed.');
    }
    foreach ($statements as $statement) {
        if (trim($statement) !== '') {
            $connection->exec($statement);
        }
    }

    $columns = [
        'employee_id' => 'CHAR(6) NULL',
        'mobile' => "VARCHAR(40) NOT NULL DEFAULT ''",
        'date_of_birth' => "DATE NOT NULL DEFAULT '1970-01-01'",
        'address' => "VARCHAR(500) NOT NULL DEFAULT ''",
        'profile_image_path' => 'VARCHAR(255) DEFAULT NULL',
    ];
    $columnCheck = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );

    foreach ($columns as $column => $definition) {
        $columnCheck->execute(['table_name' => 'users', 'column_name' => $column]);
        if ((int) $columnCheck->fetchColumn() === 0) {
            $connection->exec(sprintf('ALTER TABLE users ADD COLUMN `%s` %s', $column, $definition));
        }
    }

    $columnCheck->execute(['table_name' => 'attendance_records', 'column_name' => 'on_time_bonus']);
    if ((int) $columnCheck->fetchColumn() === 0) {
        $connection->exec(
            'ALTER TABLE attendance_records
             ADD COLUMN on_time_bonus DECIMAL(10, 2) NOT NULL DEFAULT 0.00 AFTER late_deduction'
        );
    }

    $columnCheck->execute(['table_name' => 'daily_activities', 'column_name' => 'bonus_amount']);
    if ((int) $columnCheck->fetchColumn() === 0) {
        $connection->exec(
            'ALTER TABLE daily_activities
             ADD COLUMN bonus_amount DECIMAL(10, 2) NULL AFTER platform'
        );
    }

    $leaveTypeCheck = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $leaveTypeCheck->execute(['table_name' => 'telecaller_leads', 'column_name' => 'lead_type']);
    if ((int) $leaveTypeCheck->fetchColumn() > 0) {
        $connection->exec('ALTER TABLE telecaller_leads DROP COLUMN lead_type');
    }

    $leaveTypeCheck->execute(['table_name' => 'leave_requests', 'column_name' => 'leave_type']);
    if ((int) $leaveTypeCheck->fetchColumn() === 0) {
        $connection->exec(
            "ALTER TABLE leave_requests
             ADD COLUMN leave_type ENUM('casual', 'sick', 'emergency', 'personal')
             NOT NULL DEFAULT 'casual' AFTER user_id"
        );
    }

    $activityStatusColumn = $connection->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_activities' AND COLUMN_NAME = 'status'"
    )->fetchColumn();
    if (is_string($activityStatusColumn) && strpos($activityStatusColumn, "'rejected'") === false) {
        $connection->exec(
            "ALTER TABLE daily_activities
             MODIFY status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending'"
        );
    }

    foreach ([
        'reviewed_at' => 'DATETIME NULL AFTER verified_at',
        'rejection_reason' => 'VARCHAR(500) NULL AFTER reviewed_at',
    ] as $column => $definition) {
        $columnCheck->execute(['table_name' => 'daily_activities', 'column_name' => $column]);
        if ((int) $columnCheck->fetchColumn() === 0) {
            $connection->exec(sprintf('ALTER TABLE daily_activities ADD COLUMN `%s` %s', $column, $definition));
        }
    }

    foreach ([
        'bank_name' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER total_budget",
        'account_holder_name' => "VARCHAR(120) NOT NULL DEFAULT '' AFTER bank_name",
        'account_number' => "VARCHAR(34) NOT NULL DEFAULT '' AFTER account_holder_name",
        'ifsc_code' => "CHAR(11) NOT NULL DEFAULT '' AFTER account_number",
    ] as $column => $definition) {
        $columnCheck->execute(['table_name' => 'company_budgets', 'column_name' => $column]);
        if ((int) $columnCheck->fetchColumn() === 0) {
            $connection->exec(sprintf('ALTER TABLE company_budgets ADD COLUMN `%s` %s', $column, $definition));
        }
    }

    $users = $connection->query('SELECT id, employee_id FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $seenEmployeeIds = [];
    $updateEmployeeId = $connection->prepare('UPDATE users SET employee_id = :employee_id WHERE id = :id');
    foreach ($users as $user) {
        $employeeId = is_string($user['employee_id']) ? $user['employee_id'] : '';
        if (preg_match('/^[0-9]{6}$/', $employeeId) === 1 && !isset($seenEmployeeIds[$employeeId])) {
            $seenEmployeeIds[$employeeId] = true;
            continue;
        }

        $employeeId = \Arowin\Modules\Employees\EmployeeIdGenerator::generate($connection);
        $updateEmployeeId->execute(['employee_id' => $employeeId, 'id' => $user['id']]);
        $seenEmployeeIds[$employeeId] = true;
    }

    $employeeIdIndexCheck = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
    );
    $employeeIdIndexCheck->execute(['table_name' => 'users', 'index_name' => 'uq_users_employee_id']);
    if ((int) $employeeIdIndexCheck->fetchColumn() === 0) {
        $connection->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_employee_id (employee_id)');
    }

    $employeeIdColumnCheck = $connection->prepare(
        'SELECT IS_NULLABLE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $employeeIdColumnCheck->execute(['table_name' => 'users', 'column_name' => 'employee_id']);
    if ($employeeIdColumnCheck->fetchColumn() === 'YES') {
        $connection->exec('ALTER TABLE users MODIFY employee_id CHAR(6) NOT NULL');
    }

    if (!$schemaOnly) {
        $seed = $connection->prepare(
            "INSERT INTO users (employee_id, name, email, password_hash, role, is_active)
             VALUES (:employee_id, :name, :email, :password_hash, 'sub_editor', 1)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $seed->execute([
            'employee_id' => \Arowin\Modules\Employees\EmployeeIdGenerator::generate($connection),
            'name' => $adminName,
            'email' => $adminEmail,
            'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
        ]);
    }

    fwrite(STDOUT, $schemaOnly
        ? "Database schema is ready. Existing accounts were not modified.\n"
        : "Database schema is ready. Initial Sub Editor account: {$adminEmail}\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Database setup failed: {$error->getMessage()}\n");
    exit(1);
}
