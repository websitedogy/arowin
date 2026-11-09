<?php

declare(strict_types=1);

namespace Arowin\Shared;

use DateTimeImmutable;
use DateTimeZone;

final class AppTime
{
    private const TIME_ZONE = 'Asia/Kolkata';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::timeZone());
    }

    public static function timeZone(): DateTimeZone
    {
        return new DateTimeZone(self::TIME_ZONE);
    }
}
