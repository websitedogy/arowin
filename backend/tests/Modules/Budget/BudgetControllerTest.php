<?php

declare(strict_types=1);

use Arowin\Modules\Budget\BudgetController;
use PHPUnit\Framework\TestCase;

final class BudgetControllerTest extends TestCase
{
    public function testAccountingEntryCalculatesTotalAndIsScopedToItsCategory(): void
    {
        $database = $this->createDatabase();
        $controller = new BudgetController($database);

        $entry = $controller->saveAccountingEntry('office', [
            'itemName' => 'Desk lamp',
            'quantity' => '1.5',
            'pricePerItem' => '10.01',
            'note' => 'For the meeting room',
        ], null, 7);
        $assetEntry = $controller->saveAccountingEntry('assets', [
            'itemName' => 'Laptop',
            'quantity' => '1',
            'pricePerItem' => '100.00',
        ], null, 7);

        self::assertSame('15.02', $entry['totalPrice']);
        self::assertSame('1.500', $entry['quantity']);
        self::assertSame('For the meeting room', $entry['note']);
        self::assertCount(1, $controller->listAccountingEntries('office'));
        self::assertSame('100.00', $assetEntry['totalPrice']);
        self::assertCount(1, $controller->listAccountingEntries('assets'));
    }

    public function testAccountingEntryRejectsZeroQuantityAndUnsupportedCategories(): void
    {
        $controller = new BudgetController($this->createDatabase());

        try {
            $controller->saveAccountingEntry('office', [
                'itemName' => 'Desk lamp',
                'quantity' => '0',
                'pricePerItem' => '10.01',
            ], null, 7);
            self::fail('A zero quantity should be rejected.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Enter a valid quantity greater than zero.', $error->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $controller->listAccountingEntries('expenses');
    }

    public function testAccountingEntryCanBeEditedAndDeletedWithoutChangingOtherCategory(): void
    {
        $controller = new BudgetController($this->createDatabase());
        $officeEntry = $controller->saveAccountingEntry('office', [
            'itemName' => 'Desk',
            'quantity' => '1',
            'pricePerItem' => '200.00',
        ], null, 7);
        $assetEntry = $controller->saveAccountingEntry('assets', [
            'itemName' => 'Monitor',
            'quantity' => '1',
            'pricePerItem' => '150.00',
        ], null, 7);

        $updated = $controller->updateAccountingEntry('office', $officeEntry['id'], [
            'itemName' => 'Standing desk',
            'quantity' => '2',
            'pricePerItem' => '250.00',
            'note' => 'Updated item details',
        ], null);
        self::assertSame('500.00', $updated['totalPrice']);
        self::assertSame('Standing desk', $updated['itemName']);

        $controller->deleteAccountingEntry('office', $officeEntry['id']);
        self::assertSame([], $controller->listAccountingEntries('office'));
        self::assertSame('Monitor', $controller->listAccountingEntries('assets')[0]['itemName']);
        self::assertSame($assetEntry['id'], $controller->listAccountingEntries('assets')[0]['id']);
    }

    public function testCostEntriesAreSavedAndEditedInTheirOwnCategoryTables(): void
    {
        $controller = new BudgetController($this->createDatabase());
        $expense = $controller->saveCostEntry('expenses', [
            'entryName' => 'Office travel',
            'amount' => '1250.5',
            'note' => 'Client visit',
        ], null, 7);
        $promotion = $controller->saveCostEntry('ads_promotions', [
            'entryName' => 'Social media campaign',
            'amount' => '3000.00',
        ], null, 7);

        self::assertSame('1250.50', $expense['amount']);
        self::assertSame('Client visit', $expense['note']);
        self::assertCount(1, $controller->listCostEntries('expenses'));
        self::assertSame('3000.00', $controller->listCostEntries('ads_promotions')[0]['amount']);

        $updated = $controller->updateCostEntry('expenses', $expense['id'], [
            'entryName' => 'Local travel',
            'amount' => '1800',
            'note' => '',
        ], null);
        self::assertSame('Local travel', $updated['entryName']);
        self::assertSame('1800.00', $updated['amount']);

        $controller->deleteCostEntry('expenses', $expense['id']);
        self::assertSame([], $controller->listCostEntries('expenses'));
        self::assertSame('Social media campaign', $controller->listCostEntries('ads_promotions')[0]['entryName']);
    }

    public function testCostEntryRejectsInvalidCategoryAndAmount(): void
    {
        $controller = new BudgetController($this->createDatabase());
        try {
            $controller->saveCostEntry('expenses', [
                'entryName' => 'Office travel',
                'amount' => '0',
            ], null, 7);
            self::fail('A zero amount should be rejected.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Enter a valid amount.', $error->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $controller->listCostEntries('office');
    }

    public function testAdvanceIsSavedEditedAndDeletedWithReturnAndExpiryDetails(): void
    {
        $controller = new BudgetController($this->createDatabase());
        $advance = $controller->saveAdvance([
            'paidTo' => 'Sam',
            'reason' => 'Travel allowance',
            'amount' => '2500',
            'paymentMode' => 'upi',
            'isReturnable' => 'yes',
            'hasExpiryDate' => 'yes',
            'expiryDate' => '2026-12-31',
        ], 7);

        self::assertSame('2500.00', $advance['amount']);
        self::assertSame('upi', $advance['paymentMode']);
        self::assertTrue($advance['isReturnable']);
        self::assertTrue($advance['hasExpiryDate']);
        self::assertSame('2026-12-31', $advance['expiryDate']);

        $updated = $controller->updateAdvance($advance['id'], [
            'paidTo' => 'Sam',
            'reason' => 'Non-returnable allowance',
            'amount' => '3000',
            'paymentMode' => 'net_banking',
            'isReturnable' => 'no',
            'hasExpiryDate' => 'no',
            'expiryDate' => '',
        ]);
        self::assertSame('3000.00', $updated['amount']);
        self::assertFalse($updated['isReturnable']);
        self::assertFalse($updated['hasExpiryDate']);
        self::assertNull($updated['expiryDate']);

        $controller->deleteAdvance($advance['id']);
        self::assertSame([], $controller->listAdvances());
    }

    public function testAdvanceRejectsInvalidPaymentModeAndExpiryDate(): void
    {
        $controller = new BudgetController($this->createDatabase());
        try {
            $controller->saveAdvance([
                'paidTo' => 'Sam',
                'reason' => 'Travel',
                'amount' => '2500',
                'paymentMode' => 'crypto',
                'isReturnable' => 'yes',
                'hasExpiryDate' => 'no',
                'expiryDate' => '',
            ], 7);
            self::fail('Unsupported payment methods should be rejected.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Choose a valid payment method.', $error->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $controller->saveAdvance([
            'paidTo' => 'Sam',
            'reason' => 'Travel',
            'amount' => '2500',
            'paymentMode' => 'cash',
            'isReturnable' => 'yes',
            'hasExpiryDate' => 'yes',
            'expiryDate' => '2026-02-31',
        ], 7);
    }

    public function testSalaryListingIncludesSalaryCreationTime(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec(
            "CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                employee_id TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                role TEXT NOT NULL
            )"
        );
        $database->exec(
            "CREATE TABLE employee_salaries (
                user_id INTEGER PRIMARY KEY,
                joining_date TEXT NOT NULL,
                salary_amount TEXT NOT NULL,
                created_at TEXT NOT NULL
            )"
        );
        $database->exec(
            "INSERT INTO users (id, employee_id, name, email, role)
             VALUES (9, '123456', 'Sam', 'sam@example.com', 'telecaller')"
        );
        $database->exec(
            "INSERT INTO employee_salaries (user_id, joining_date, salary_amount, created_at)
             VALUES (9, '2026-01-15', '42000.00', '2026-01-10 10:25:00')"
        );

        $salaries = (new BudgetController($database))->listSalaries();

        self::assertSame('2026-01-10 10:25:00', $salaries[0]['createdAt']);
    }

    private function createDatabase(): PDO
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('PRAGMA foreign_keys = ON');
        $database->exec(
            "CREATE TABLE budget_advances (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                paid_to TEXT NOT NULL,
                reason TEXT NOT NULL,
                amount TEXT NOT NULL,
                payment_mode TEXT NOT NULL,
                is_returnable INTEGER NOT NULL,
                has_expiry_date INTEGER NOT NULL,
                expiry_date TEXT,
                created_by INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        foreach (['expenses', 'ads_promotions'] as $category) {
            $database->exec(
                "CREATE TABLE {$category}_entries (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entry_name TEXT NOT NULL,
                    amount TEXT NOT NULL,
                    note TEXT NOT NULL DEFAULT '',
                    created_by INTEGER,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $database->exec(
                "CREATE TABLE {$category}_proofs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entry_id INTEGER NOT NULL,
                    file_name TEXT NOT NULL DEFAULT '',
                    original_name TEXT NOT NULL,
                    mime_type TEXT NOT NULL,
                    file_size INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )"
            );
        }
        foreach (['office', 'assets'] as $category) {
            $database->exec(
                "CREATE TABLE {$category}_accounting_entries (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    item_name TEXT NOT NULL,
                    quantity TEXT NOT NULL,
                    price_per_item TEXT NOT NULL,
                    total_price TEXT NOT NULL,
                    note TEXT NOT NULL DEFAULT '',
                    created_by INTEGER,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $database->exec(
                "CREATE TABLE {$category}_accounting_proofs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    entry_id INTEGER NOT NULL,
                    file_name TEXT NOT NULL DEFAULT '',
                    original_name TEXT NOT NULL,
                    mime_type TEXT NOT NULL,
                    file_size INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )"
            );
        }

        return $database;
    }
}
