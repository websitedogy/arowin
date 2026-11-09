<?php

declare(strict_types=1);

namespace Arowin;

class OtpGenerator
{
    public function generate(int $digits = 6): string
    {
        $minimum = (int) str_repeat('1', $digits);
        $maximum = (int) str_repeat('9', $digits);
        $value = random_int($minimum, $maximum);
        return str_pad((string) $value, $digits, '0', STR_PAD_LEFT);
    }
}
