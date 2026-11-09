<?php

declare(strict_types=1);

use Arowin\Modules\Employees\EmployeeIdGenerator;
use PHPUnit\Framework\TestCase;

final class EmployeeIdGeneratorTest extends TestCase
{
    public function testGenerateReturnsSixDigitUnusedEmployeeId(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, employee_id TEXT UNIQUE)');

        $generatedIds = [];
        for ($index = 0; $index < 25; $index++) {
            $employeeId = EmployeeIdGenerator::generate($database);

            $this->assertMatchesRegularExpression('/^\d{6}$/', $employeeId);
            $this->assertArrayNotHasKey($employeeId, $generatedIds);
            $generatedIds[$employeeId] = true;
            $this->assertSame(0, (int) $database->query("SELECT COUNT(*) FROM users WHERE employee_id = '{$employeeId}'")->fetchColumn());
        }
    }
}
