<?php

declare(strict_types=1);

use Arowin\Modules\Leads\LeadController;
use PHPUnit\Framework\TestCase;

final class LeadControllerTest extends TestCase
{
    public function testSubEditorCanListAllTelecallerLeadsWithOwnerDetails(): void
    {
        $database = $this->createDatabase();
        $database->exec("INSERT INTO users (id, employee_id, name, role) VALUES
            (1, '200001', 'First Caller', 'telecaller'),
            (2, '200002', 'Second Caller', 'telecaller'),
            (3, '100001', 'Sub Editor', 'sub_editor')");
        $database->exec("INSERT INTO telecaller_leads
            (id, telecaller_id, customer_name, mobile_number, state_code, state_name, district,
             email, address, date_of_birth, gender, note, created_at) VALUES
            (1, 1, 'Older Customer', '9000000001', NULL, NULL, 'North', '', '', NULL, NULL, '', '2025-01-01 10:00:00'),
            (2, 2, 'Recent Customer', '9000000002', 'AP', 'Andhra Pradesh', 'Anantapuram',
             'customer@example.com', 'Main Road', '1990-01-01', 'female', 'Call tomorrow', '2025-01-02 10:00:00')");

        $leads = (new LeadController($database))->listAllForSubEditor();

        self::assertCount(2, $leads);
        self::assertSame('Second Caller', $leads[0]['employeeName']);
        self::assertSame('200002', $leads[0]['employeeId']);
        self::assertSame('Recent Customer', $leads[0]['customerName']);
        self::assertSame('Andhra Pradesh', $leads[0]['stateName']);
        self::assertSame('customer@example.com', $leads[0]['email']);
        self::assertSame('female', $leads[0]['gender']);
        self::assertMatchesRegularExpression('/^LD-[0-9]{6}$/', $leads[0]['leadId']);
    }

    public function testCreateStoresTheSelectedTerritoryAndContactDetails(): void
    {
        $database = $this->createDatabase();

        $lead = (new LeadController($database))->create(1, [
            'customerName' => 'New Customer',
            'mobileNumber' => '+91 98765 43210',
            'stateCode' => 'AP',
            'district' => 'Anantapuram',
            'email' => 'new@example.com',
            'address' => '12 Main Road',
            'dateOfBirth' => '1990-05-10',
            'gender' => 'male',
            'note' => 'Call after 5 PM',
        ]);

        self::assertSame('Andhra Pradesh', $lead['stateName']);
        self::assertSame('Anantapuram', $lead['district']);
        self::assertSame('new@example.com', $lead['email']);
        self::assertSame('12 Main Road', $lead['address']);
        self::assertNotNull($lead['age']);
        self::assertSame('male', $lead['gender']);
        self::assertSame('LD-000001', $lead['leadId']);
    }

    public function testCreateRejectsDistrictsThatDoNotBelongToTheSelectedState(): void
    {
        $database = $this->createDatabase();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Choose a valid district for the selected state.');

        (new LeadController($database))->create(1, [
            'customerName' => 'New Customer',
            'mobileNumber' => '9876543210',
            'stateCode' => 'AP',
            'district' => 'Chennai',
            'email' => 'new@example.com',
            'address' => '12 Main Road',
            'dateOfBirth' => '1990-05-10',
            'gender' => 'female',
        ]);
    }

    private function createDatabase(): \PDO
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
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            telecaller_id INTEGER NOT NULL,
            customer_name TEXT NOT NULL,
            mobile_number TEXT NOT NULL,
            state_code TEXT,
            state_name TEXT,
            district TEXT NOT NULL,
            email TEXT NOT NULL DEFAULT \'\',
            address TEXT NOT NULL DEFAULT \'\',
            date_of_birth TEXT,
            gender TEXT,
            note TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');

        return $database;
    }
}
