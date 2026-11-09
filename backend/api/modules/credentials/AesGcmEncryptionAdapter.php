<?php

declare(strict_types=1);

namespace Arowin\Modules\Credentials;

use Arowin\EncryptionPort;
use RuntimeException;

final class AesGcmEncryptionAdapter implements EncryptionPort
{
    public function __construct(private readonly string $key)
    {
        if (strlen($key) !== 32) {
            throw new RuntimeException('The encryption key must be exactly 32 bytes long.');
        }
    }

    public static function fromBase64Env(string $envName): self
    {
        $configuredKey = getenv($envName);
        $decoded = is_string($configuredKey) ? base64_decode($configuredKey, true) : false;
        if (!is_string($decoded) || strlen($decoded) !== 32) {
            throw new RuntimeException(sprintf('Set %s to a base64-encoded 32-byte key.', $envName));
        }

        return new self($decoded);
    }

    public function encrypt(string $value): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($value, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $nonce, $tag);
        if (!is_string($cipherText)) {
            throw new RuntimeException('Could not encrypt the value securely.');
        }

        return base64_encode($nonce . $tag . $cipherText);
    }

    public function decrypt(string $value): string
    {
        $payload = base64_decode($value, true);
        if (!is_string($payload) || strlen($payload) < 29) {
            throw new RuntimeException('Stored data could not be decrypted.');
        }

        $plainText = openssl_decrypt(
            substr($payload, 28),
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16)
        );
        if (!is_string($plainText)) {
            throw new RuntimeException('Stored data could not be decrypted.');
        }

        return $plainText;
    }
}
