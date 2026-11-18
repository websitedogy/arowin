<?php

declare(strict_types=1);

namespace Arowin\Modules\Employees;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class EmployeeJoiningLetterController
{
    private readonly string $storageRoot;

    public function __construct(private readonly PDO $database, ?string $storageRoot = null)
    {
        $this->storageRoot = $storageRoot
            ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'employee-joining-letters';
    }

    public function get(int $userId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT original_name, mime_type, file_size, uploaded_at
             FROM employee_joining_letters WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'name' => (string) $row['original_name'],
            'mimeType' => (string) $row['mime_type'],
            'size' => (int) $row['file_size'],
            'uploadedAt' => (string) $row['uploaded_at'],
        ];
    }

    public function upload(int $userId, mixed $upload): array
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('Sign in as a Telecaller to upload your joining letter.');
        }
        $file = $this->validateUpload($upload);
        $directory = $this->storageRoot . DIRECTORY_SEPARATOR . $userId;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Private joining letter storage could not be created.');
        }

        $extension = match ($file['mimeType']) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
        };
        $fileName = bin2hex(random_bytes(24)) . '.' . $extension;
        $destination = $directory . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($file['tmpName'], $destination)) {
            throw new RuntimeException('Your joining letter could not be saved. Please try uploading again.');
        }
        if (!chmod($destination, 0600)) {
            if (!unlink($destination)) {
                error_log('Could not remove a joining letter after setting private permissions failed.');
            }
            throw new RuntimeException('Your joining letter could not be secured in private storage.');
        }

        $oldFileName = null;
        try {
            $this->database->beginTransaction();
            $lockSuffix = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $existingQuery = $this->database->prepare(
                'SELECT file_path FROM employee_joining_letters WHERE user_id = :user_id' . $lockSuffix
            );
            $existingQuery->execute(['user_id' => $userId]);
            $existing = $existingQuery->fetchColumn();
            $oldFileName = is_string($existing) ? $existing : null;

            if ($oldFileName === null) {
                $save = $this->database->prepare(
                    'INSERT INTO employee_joining_letters
                        (user_id, file_path, original_name, mime_type, file_size, uploaded_at)
                     VALUES (:user_id, :file_path, :original_name, :mime_type, :file_size, CURRENT_TIMESTAMP)'
                );
            } else {
                $save = $this->database->prepare(
                    'UPDATE employee_joining_letters
                     SET file_path = :file_path, original_name = :original_name,
                         mime_type = :mime_type, file_size = :file_size, uploaded_at = CURRENT_TIMESTAMP
                     WHERE user_id = :user_id'
                );
            }
            $save->execute([
                'user_id' => $userId,
                'file_path' => $fileName,
                'original_name' => $file['originalName'],
                'mime_type' => $file['mimeType'],
                'file_size' => $file['size'],
            ]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            if (is_file($destination) && !unlink($destination)) {
                error_log('Could not remove an unregistered joining letter from private storage.');
            }
            throw $error;
        }

        if ($oldFileName !== null && $oldFileName !== $fileName) {
            $this->deleteStoredFile($userId, $oldFileName);
        }

        $document = $this->get($userId);
        if ($document === null) {
            throw new RuntimeException('Your joining letter was uploaded but could not be loaded.');
        }
        return $document;
    }

    public function document(int $userId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT file_path, original_name, mime_type
             FROM employee_joining_letters WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $fileName = (string) $row['file_path'];
        $mimeType = (string) $row['mime_type'];
        if (preg_match('/^[a-f0-9]{48}\.(?:pdf|jpg|png)$/', $fileName) !== 1
            || !in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw new RuntimeException('The saved joining letter has an invalid storage reference.');
        }

        $path = $this->storageRoot . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The saved joining letter could not be read from private storage.');
        }

        return [
            'path' => $path,
            'name' => (string) $row['original_name'],
            'mimeType' => $mimeType,
        ];
    }

    private function validateUpload(mixed $upload): array
    {
        if (!is_array($upload) || !isset($upload['error'])) {
            throw new InvalidArgumentException('Choose your joining letter to upload.');
        }
        if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(match ((int) $upload['error']) {
                UPLOAD_ERR_NO_FILE => 'Choose your joining letter to upload.',
                UPLOAD_ERR_PARTIAL => 'The joining letter upload was interrupted. Try again.',
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The server rejected the joining letter upload.',
                default => 'The joining letter could not be uploaded. Try again.',
            });
        }

        $temporaryPath = $upload['tmp_name'] ?? null;
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('Choose a valid joining letter file.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if ($mimeType === 'application/pdf') {
            $handle = fopen($temporaryPath, 'rb');
            $signature = $handle === false ? false : fread($handle, 5);
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($signature !== '%PDF-') {
                throw new InvalidArgumentException('The PDF joining letter is invalid.');
            }
        } elseif (in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            $imageInfo = getimagesize($temporaryPath);
            if (!is_array($imageInfo)
                || ($imageInfo['mime'] ?? '') !== $mimeType
                || (int) ($imageInfo[0] ?? 0) < 1
                || (int) ($imageInfo[1] ?? 0) < 1
                || (int) $imageInfo[0] * (int) $imageInfo[1] > 40_000_000) {
                throw new InvalidArgumentException('The joining letter is not a valid image.');
            }
        } else {
            throw new InvalidArgumentException('Joining letters must be PDF, JPEG, or PNG files.');
        }

        $size = filesize($temporaryPath);
        if (!is_int($size) || $size < 1) {
            throw new InvalidArgumentException('Choose a non-empty joining letter file.');
        }

        $originalName = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            basename(str_replace('\\', '/', (string) ($upload['name'] ?? 'joining-letter')))
        ) ?? '';
        $originalName = mb_substr(trim($originalName) ?: 'joining-letter', 0, 180);

        return [
            'tmpName' => $temporaryPath,
            'mimeType' => $mimeType,
            'originalName' => $originalName,
            'size' => $size,
        ];
    }

    private function deleteStoredFile(int $userId, string $fileName): void
    {
        if (preg_match('/^[a-f0-9]{48}\.(?:pdf|jpg|png)$/', $fileName) !== 1) {
            error_log('Could not remove a joining letter with an invalid storage reference.');
            return;
        }
        $path = $this->storageRoot . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($path) && !unlink($path)) {
            error_log('Could not remove a replaced joining letter from private storage.');
        }
    }
}
