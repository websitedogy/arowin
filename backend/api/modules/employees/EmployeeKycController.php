<?php

declare(strict_types=1);

namespace Arowin\Modules\Employees;

use Arowin\EncryptionPort;
use Arowin\Modules\Credentials\AesGcmEncryptionAdapter;
use DomainException;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class EmployeeKycController
{
    private const MAX_DOCUMENT_BYTES = 5_000_000;

    private readonly KycRepository $repository;
    private readonly DocumentStoragePort $documentStorage;
    private readonly EncryptionPort $encryption;

    public function __construct(
        private readonly PDO $database,
        ?KycRepository $repository = null,
        ?DocumentStoragePort $documentStorage = null,
        ?EncryptionPort $encryption = null
    ) {
        $this->repository = $repository ?? new PdoKycRepository($database);
        $this->documentStorage = $documentStorage ?? new LocalDocumentStorageAdapter();
        $this->encryption = $encryption ?? new AesGcmEncryptionAdapter($this->encryptionKey());
    }

    public function get(int $userId): array
    {
        $row = $this->repository->findByUserId($userId);
        if (!is_array($row)) {
            throw new RuntimeException('The employee profile could not be found.');
        }

        $hasKyc = is_string($row['status'] ?? null) && $row['status'] !== '';
        return [
            'profile' => [
                'fullName' => $hasKyc ? (string) $row['full_name'] : (string) $row['name'],
                'mobile' => $hasKyc ? (string) $row['kyc_mobile'] : (string) $row['mobile'],
                'email' => $hasKyc ? (string) $row['kyc_email'] : (string) $row['email'],
            ],
            'bank' => $hasKyc ? [
                'bankName' => (string) $row['bank_name'],
                'accountHolderName' => (string) $row['account_holder_name'],
                'accountNumber' => $this->decrypt((string) $row['account_number_encrypted']),
                'ifscCode' => (string) $row['ifsc_code'],
                'branchName' => (string) $row['branch_name'],
            ] : null,
            'identity' => $hasKyc ? [
                'aadhaarNumber' => $this->decrypt((string) $row['aadhaar_number_encrypted']),
                'panNumber' => $this->decrypt((string) $row['pan_number_encrypted']),
                'aadhaarDocumentName' => $row['aadhaar_document_name'],
                'panDocumentName' => $row['pan_document_name'],
            ] : null,
            'office' => [
                'employeeId' => (string) $row['employee_id'],
                'role' => match ((string) $row['role']) {
                    'team_leader' => 'Team Leader',
                    'sub_editor' => 'Sub Editor',
                    default => 'Telecaller',
                },
                'joiningDate' => $row['joining_date'],
                'officeAddress' => $row['office_address'],
            ],
            'status' => $hasKyc ? (string) $row['status'] : 'not_submitted',
            'submittedAt' => $row['submitted_at'],
        ];
    }

    public function submit(int $userId, array $payload, mixed $aadhaarUpload, mixed $panUpload): array
    {
        $profile = [
            'fullName' => $this->requiredText($payload, 'fullName', 'Enter your full name.', 120),
            'mobile' => $this->requiredText($payload, 'mobile', 'Enter your mobile number.', 40),
            'email' => strtolower($this->requiredText($payload, 'email', 'Enter your email address.', 180)),
        ];
        if (!filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (preg_match('/^[0-9+().\-\s]{7,40}$/', $profile['mobile']) !== 1
            || strlen(preg_replace('/\D/', '', $profile['mobile']) ?? '') < 7) {
            throw new InvalidArgumentException('Enter a valid mobile number.');
        }

        $bank = [
            'bankName' => $this->requiredText($payload, 'bankName', 'Enter your bank name.', 120),
            'accountHolderName' => $this->requiredText($payload, 'accountHolderName', 'Enter the account holder name.', 120),
            'accountNumber' => preg_replace('/\s+/', '', $this->requiredText($payload, 'accountNumber', 'Enter your bank account number.', 34)) ?? '',
            'ifscCode' => strtoupper($this->requiredText($payload, 'ifscCode', 'Enter your IFSC code.', 11)),
            'branchName' => $this->requiredText($payload, 'branchName', 'Enter your bank branch.', 120),
        ];
        if (preg_match('/^[0-9]{8,34}$/', $bank['accountNumber']) !== 1) {
            throw new InvalidArgumentException('Enter a valid bank account number (8–34 digits).');
        }
        if (preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $bank['ifscCode']) !== 1) {
            throw new InvalidArgumentException('Enter a valid 11-character IFSC code.');
        }

        $identity = [
            'aadhaarNumber' => preg_replace('/\s+/', '', $this->requiredText($payload, 'aadhaarNumber', 'Enter your Aadhaar number.', 12)) ?? '',
            'panNumber' => strtoupper($this->requiredText($payload, 'panNumber', 'Enter your PAN number.', 10)),
        ];
        if (preg_match('/^[2-9][0-9]{11}$/', $identity['aadhaarNumber']) !== 1) {
            throw new InvalidArgumentException('Enter a valid 12-digit Aadhaar number.');
        }
        if (preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $identity['panNumber']) !== 1) {
            throw new InvalidArgumentException('Enter a valid PAN number.');
        }

        $current = $this->repository->findExistingDocuments($userId);
        $files = [
            'aadhaar' => $this->validateDocument($aadhaarUpload, $current['aadhaar'] ?? null),
            'pan' => $this->validateDocument($panUpload, $current['pan'] ?? null),
        ];
        foreach ($files as $type => $file) {
            if ($file === null) {
                throw new InvalidArgumentException(sprintf('Upload a valid %s document.', ucfirst($type)));
            }
        }

        $documentRecords = $current;
        foreach ($files as $type => $file) {
            $stored = $this->documentStorage->store((string) $userId, $file, $type);
            $documentRecords[$type] = [
                'path' => $stored['path'],
                'name' => $stored['name'],
                'mimeType' => $stored['mimeType'],
            ];
            $previousPath = $current[$type]['path'] ?? null;
            if (is_string($previousPath) && $previousPath !== $documentRecords[$type]['path']) {
                $this->documentStorage->delete((string) $userId, $previousPath);
            }
        }

        $result = $this->repository->saveSubmission($userId, $profile, [
            'bankName' => $bank['bankName'],
            'accountHolderName' => $bank['accountHolderName'],
            'accountNumberEncrypted' => $this->encryption->encrypt($bank['accountNumber']),
            'ifscCode' => $bank['ifscCode'],
            'branchName' => $bank['branchName'],
        ], [
            'aadhaarEncrypted' => $this->encryption->encrypt($identity['aadhaarNumber']),
            'panEncrypted' => $this->encryption->encrypt($identity['panNumber']),
        ], $documentRecords);

        return $this->get($userId);
    }

    private function existingDocuments(int $userId): array
    {
        return $this->repository->findExistingDocuments($userId);
    }

    private function validateDocument(mixed $upload, ?array $current): ?array
    {
        if (!is_array($upload) || !isset($upload['error'])) {
            return $current;
        }
        if ((int) $upload['error'] === UPLOAD_ERR_NO_FILE) {
            return $current;
        }
        if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(match ((int) $upload['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Each identity document must be smaller than 5 MB.',
                UPLOAD_ERR_PARTIAL => 'An identity document upload was interrupted. Try again.',
                default => 'An identity document could not be uploaded. Try again.',
            });
        }
        $temporaryPath = $upload['tmp_name'] ?? null;
        if (!is_string($temporaryPath) || (!is_uploaded_file($temporaryPath) && !is_file($temporaryPath))) {
            throw new InvalidArgumentException('Choose a valid Aadhaar or PAN document.');
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_DOCUMENT_BYTES) {
            throw new InvalidArgumentException('Each identity document must be smaller than 5 MB.');
        }
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if ($mimeType === 'application/pdf') {
            $handle = fopen($temporaryPath, 'rb');
            $signature = $handle === false ? false : fread($handle, 5);
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($signature !== '%PDF-') {
                throw new InvalidArgumentException('The PDF identity document is invalid.');
            }
        } elseif (in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            $imageInfo = getimagesize($temporaryPath);
            if (!is_array($imageInfo)
                || ($imageInfo['mime'] ?? '') !== $mimeType
                || (int) ($imageInfo[0] ?? 0) < 1
                || (int) ($imageInfo[1] ?? 0) < 1
                || (int) $imageInfo[0] * (int) $imageInfo[1] > 40_000_000) {
                throw new InvalidArgumentException('The identity document is not a valid image.');
            }
        } else {
            throw new InvalidArgumentException('Identity documents must be JPEG, PNG, or PDF files.');
        }

        return [
            'tmp_name' => $temporaryPath,
            'mimeType' => $mimeType,
            'originalName' => $this->safeOriginalName((string) ($upload['name'] ?? 'identity-document')),
        ];
    }

    private function requiredText(array $payload, string $key, string $message, int $maxLength): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value)) {
            throw new InvalidArgumentException($message);
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException($message);
        }
        return $value;
    }

    private function safeOriginalName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $name))) ?? '';
        $name = trim($name);
        return mb_substr($name === '' ? 'identity-document' : $name, 0, 180);
    }

    private function encrypt(string $value): string
    {
        return $this->encryption->encrypt($value);
    }

    private function decrypt(string $value): string
    {
        return $this->encryption->decrypt($value);
    }

    private function encryptionKey(): string
    {
        $directory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'employee-kyc';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Private KYC storage could not be created.');
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'encryption.key';
        if (!is_file($path)) {
            $handle = @fopen($path, 'x');
            if (is_resource($handle)) {
                $key = random_bytes(32);
                $written = fwrite($handle, $key);
                fflush($handle);
                fclose($handle);
                @chmod($path, 0600);
                if ($written !== 32) {
                    throw new RuntimeException('The private KYC encryption key could not be saved.');
                }
            }
        }
        $key = file_get_contents($path);
        if (!is_string($key) || strlen($key) !== 32) {
            throw new RuntimeException('The private KYC encryption key is missing or invalid.');
        }
        return $key;
    }
}
