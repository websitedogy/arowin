<?php

declare(strict_types=1);

namespace Arowin\Modules\Credentials;

interface MailerPort
{
    public function sendEmployeeCredentials(
        string $email,
        string $name,
        string $role,
        string $employeeId,
        string $password,
        string $appUrl,
        string $installUrl
    ): void;
}
