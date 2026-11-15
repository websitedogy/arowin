<?php

declare(strict_types=1);

use Arowin\Modules\Sales\GlobalCustomerLookup;
use PHPUnit\Framework\TestCase;

final class GlobalCustomerLookupTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE node_registrations (
            username TEXT PRIMARY KEY,
            full_name TEXT NOT NULL,
            purchased_package TEXT,
            node_package TEXT,
            status TEXT NOT NULL
        )');
        $this->database->exec("INSERT INTO node_registrations VALUES
            ('arowin-10001', 'Active Customer', '350', '3150', 'active'),
            ('arowin-10002', 'Pending Customer', NULL, '150', 'pending'),
            ('arowin-10003', 'No Package Customer', NULL, NULL, 'active')");
    }

    public function testFindsActiveCustomerAndFormatsPurchasedPackage(): void
    {
        $customer = (new GlobalCustomerLookup($this->database))->lookupCustomer('arowin-10001');

        $this->assertSame([
            'customerId' => 'arowin-10001',
            'customerName' => 'Active Customer',
            'packageAmount' => '350.00',
            'activationStatus' => 'active',
            'isEmployeeCredited' => true,
            'canSubmit' => true,
            'message' => 'Customer details verified from Arowin Global.',
        ], $customer);
    }

    public function testPendingCustomerCanSubmitSelectedPackageWithoutEmployeeCredit(): void
    {
        $customer = (new GlobalCustomerLookup($this->database))->lookupCustomer('arowin-10002');

        $this->assertSame('150.00', $customer['packageAmount']);
        $this->assertSame('pending', $customer['activationStatus']);
        $this->assertFalse($customer['isEmployeeCredited']);
        $this->assertTrue($customer['canSubmit']);
        $this->assertStringContainsString('will not count toward employee sales or incentives', $customer['message']);
    }

    public function testCustomerWithoutPackageCanVerifyEmailButCannotSubmitSale(): void
    {
        $customer = (new GlobalCustomerLookup($this->database))->lookupCustomer('arowin-10003');

        $this->assertNull($customer['packageAmount']);
        $this->assertFalse($customer['isEmployeeCredited']);
        $this->assertFalse($customer['canSubmit']);
        $this->assertStringContainsString('Ask the customer to purchase a package', $customer['message']);
    }

    public function testUnknownCustomerIdIsReportedAsNotFound(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Customer ID was not found in Arowin Global.');

        (new GlobalCustomerLookup($this->database))->lookupCustomer('missing');
    }

    public function testRejectedCustomerIdIsReportedAsInactive(): void
    {
        $this->database->exec("INSERT INTO node_registrations VALUES ('arowin-10004', 'Rejected Customer', NULL, '150', 'rejected')");

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This customer ID is not active in Arowin Global.');

        (new GlobalCustomerLookup($this->database))->lookupCustomer('arowin-10004');
    }
}
