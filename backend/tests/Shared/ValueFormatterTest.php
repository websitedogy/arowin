<?php

declare(strict_types=1);

use Arowin\Shared\ValueFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueFormatterTest extends TestCase
{
    public static function validSalesAmountsProvider(): array
    {
        return [
            ['0'],
            ['1'],
            ['2500'],
            ['1000.50'],
            ['999999999999.99'],
        ];
    }

    #[DataProvider('validSalesAmountsProvider')]
    public function testIsValidSalesAmountAcceptsValidAmounts(string $amount): void
    {
        $this->assertTrue(ValueFormatter::isValidSalesAmount($amount));
    }

    public static function invalidSalesAmountsProvider(): array
    {
        return [
            [''],
            ['0.000'],
            ['01'],
            ['-1'],
            ['1.234'],
            ['abc'],
            ['12345678901234'],
            ['1.'],
            ['.50'],
        ];
    }

    #[DataProvider('invalidSalesAmountsProvider')]
    public function testIsValidSalesAmountRejectsInvalidAmounts(string $amount): void
    {
        $this->assertFalse(ValueFormatter::isValidSalesAmount($amount));
    }

    public function testFormatAmountNormalizesToTwoDecimalPlaces(): void
    {
        $this->assertSame('250.00', ValueFormatter::formatAmount('250'));
        $this->assertSame('1250.00', ValueFormatter::formatAmount(1250));
        $this->assertSame('1234.50', ValueFormatter::formatAmount(1234.5));
    }

    public function testNormalizeMonthAcceptsValidYearMonthStrings(): void
    {
        $this->assertSame('2025-01-01', ValueFormatter::normalizeMonth('2025-01', 'Invalid month.'));
        $this->assertSame('2024-12-01', ValueFormatter::normalizeMonth('2024-12', 'Invalid month.'));
    }

    public static function invalidMonthsProvider(): array
    {
        return [
            ['2025'],
            ['2025-00'],
            ['2025-13'],
            ['2025-1'],
            ['2025-1-01'],
            ['invalid'],
        ];
    }

    #[DataProvider('invalidMonthsProvider')]
    public function testNormalizeMonthRejectsInvalidYearMonthStrings(string $month): void
    {
        $this->expectException(InvalidArgumentException::class);

        ValueFormatter::normalizeMonth($month, 'Invalid month.');
    }
}
