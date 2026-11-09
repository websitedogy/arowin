<?php

declare(strict_types=1);

namespace Arowin\Modules\Employees;

use RuntimeException;

final class LocalDocumentStorageAdapter implements DocumentStoragePort
{
    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'employee-kyc';
    }

    public function store(string $userId, array $file, string $type): array
    {
        $directory = $this->root . DIRECTORY_SEPARATOR . $userId;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Private KYC document storage could not be created.');
        }

        $extension = $file['mimeType'] === 'application/pdf'
            ? 'pdf'
            : ($file['mimeType'] === 'image/png' ? 'png' : 'jpg');
        $fileName = bin2hex(random_bytes(24)) . '.' . $extension;
        $destination = $directory . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $source = $file['tmp_name'];
            if (!is_string($source) || !is_file($source) || !rename($source, $destination)) {
                throw new RuntimeException(sprintf('The %s document could not be saved.', ucfirst($type)));
            }
        }

        return [
            'path' => $fileName,
            'name' => $file['originalName'],
            'mimeType' => $file['mimeType'],
            'fullPath' => $destination,
        ];
    }

    public function delete(string $userId, string $relativePath): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $relativePath;
        if (is_file($path) && !unlink($path)) {
            error_log(sprintf('Could not remove a replaced KYC document: %s', $relativePath));
        }
    }
}
