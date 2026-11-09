<?php

declare(strict_types=1);

namespace Arowin\Modules\Credentials;

interface MailerPort
{
    public function sendCredentialLink(string $email, string $name, string $role, string $url, string $expiresAt): void;
}
