<?php

declare(strict_types=1);

namespace Arowin\Shared;

use DateTimeImmutable;
use InvalidArgumentException;

final class ValueFormatter
{
    public static function isValidSalesAmount(string $amount): bool
    {
        return preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/', $amount) === 1;
    }

    public static function formatAmount(string|float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    public static function normalizeMonth(string $month, string $errorMessage): string
    {
        if (!preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException($errorMessage);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        if (!$date || $date->format('Y-m') !== $month) {
            throw new InvalidArgumentException($errorMessage);
        }

        return $date->format('Y-m-d');
    }
}
