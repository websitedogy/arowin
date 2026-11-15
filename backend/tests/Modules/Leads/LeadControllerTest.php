<?php

declare(strict_types=1);

use Arowin\Modules\Leads\LeadController;
use PHPUnit\Framework\TestCase;

final class LeadControllerTest extends TestCase
{
    public function testSubEditorCanListAllTelecallerLeadsWithOwnerDetails(): void
    {
        $database = new \PDO('sqlite::memory:');
        $database->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            employee_id TEXT NOT NULL,
            name TEXT NOT NULL,
            role TEXT NOT NULL
        )');
        $database->exec('CREATE TABLE telecaller_leads (
            id INTEGER PRIMARY KEY,
            telecaller_id INTEGER NOT NULL,
            customer_name TEXT NOT NULL,
            mobile_number TEXT NOT NULL,
            district TEXT NOT NULL,
            note TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');
        $database->exec("INSERT INTO users (id, employee_id, name, role) VALUES
            (1, '200001', 'First Caller', 'telecaller'),
            (2, '200002', 'Second Caller', 'telecaller'),
            (3, '100001', 'Sub Editor', 'sub_editor')");
        $database->exec("INSERT INTO telecaller_leads
            (id, telecaller_id, customer_name, mobile_number, district, note, created_at) VALUES
            (1, 1, 'Older Customer', '9000000001', 'North', '', '2025-01-01 10:00:00'),
            (2, 2, 'Recent Customer', '9000000002', 'South', 'Call tomorrow', '2025-01-02 10:00:00')");

        $leads = (new LeadController($database))->listAllForSubEditor();

        self::assertCount(2, $leads);
        self::assertSame('Second Caller', $leads[0]['employeeName']);
        self::assertSame('200002', $leads[0]['employeeId']);
        self::assertSame('Recent Customer', $leads[0]['customerName']);
    }
}
