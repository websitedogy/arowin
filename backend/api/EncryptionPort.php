<?php

declare(strict_types=1);

namespace Arowin;

interface EncryptionPort
{
    public function encrypt(string $value): string;

    public function decrypt(string $value): string;
}
