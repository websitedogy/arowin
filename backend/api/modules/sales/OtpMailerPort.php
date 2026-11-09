<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

interface OtpMailerPort
{
    public function sendUnlockOtp(string $email, string $name, string $code): void;
}
