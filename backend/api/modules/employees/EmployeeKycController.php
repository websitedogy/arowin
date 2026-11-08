<?php

declare(strict_types=1);

namespace Arowin\Modules\Employees;

use DomainException;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class EmployeeKycController
{
    private const MAX_DOCUMENT_BYTES = 5_000_000;

    public function __construct(private readonly PDO $database)
    {
    }

    public function get(int $userId): array
    {
        $statement = $this->database->prepare(
            'SELECT employee.employee_id, employee.name, employee.email, employee.mobile, employee.role,
                    salary.joining_date, office.address AS office_address,
                    kyc.full_name, kyc.email AS kyc_email, kyc.mobile AS kyc_mobile,
                    kyc.bank_name, kyc.account_holder_name, kyc.account_number_encrypted, kyc.ifsc_code,
                    kyc.branch_name, kyc.aadhaar_number_encrypted, kyc.pan_number_encrypted,
                    kyc.aadhaar_document_name, kyc.pan_document_name, kyc.status, kyc.submitted_at
             FROM users employee
             LEFT JOIN employee_salaries salary ON salary.user_id = employee.id
             LEFT JOIN office_locations office ON office.id = 1
             LEFT JOIN employee_kyc kyc ON kyc.user_id = employee.id
             WHERE employee.id = :user_id AND employee.is_active = 1
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('The employee profile could not be found.');
        }

        $hasKyc = is_string($row['status']);
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
                'role' => (string) $row['role'] === 'team_leader' ? 'Team Leader' : 'Telecaller',
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

        $current = $this->existingDocuments($userId);
        $files = [
            'aadhaar' => $this->validateDocument($aadhaarUpload, $current['aadhaar']),
            'pan' => $this->validateDocument($panUpload, $current['pan']),
        ];
        foreach ($files as $type => $file) {
            if ($file === null) {
                throw new InvalidArgumentException(sprintf('Upload a valid %s document.', ucfirst($type)));
            }
        }

        $storageDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'employee-kyc' . DIRECTORY_SEPARATOR . (string) $userId;
        if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
            throw new RuntimeException('Private KYC document storage could not be created.');
        }

        $savedPaths = [];
        $documentRecords = $current;
        try {
            foreach ($files as $type => $file) {
                if ($file === null || !isset($file['tmp_name'])) {
                    continue;
                }
                $extension = $file['mimeType'] === 'application/pdf'
                    ? 'pdf'
                    : ($file['mimeType'] === 'image/png' ? 'png' : 'jpg');
                $fileName = bin2hex(random_bytes(24)) . '.' . $extension;
                $destination = $storageDirectory . DIRECTORY_SEPARATOR . $fileName;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new RuntimeException(sprintf('The %s document could not be saved.', ucfirst($type)));
                }
                $savedPaths[] = $destination;
                $documentRecords[$type] = [
                    'path' => $fileName,
                    'name' => $file['originalName'],
                    'mimeType' => $file['mimeType'],
                ];
            }

            $this->database->beginTransaction();
            $statement = $this->database->prepare(
                "INSERT INTO employee_kyc (
                    user_id, full_name, email, mobile, bank_name, account_holder_name,
                    account_number_encrypted, ifsc_code, branch_name, aadhaar_number_encrypted,
                    pan_number_encrypted, aadhaar_document_path, aadhaar_document_name, aadhaar_document_mime,
                    pan_document_path, pan_document_name, pan_document_mime, status, submitted_at
                 ) VALUES (
                    :user_id, :full_name, :email, :mobile, :bank_name, :account_holder_name,
                    :account_number_encrypted, :ifsc_code, :branch_name, :aadhaar_number_encrypted,
                    :pan_number_encrypted, :aadhaar_document_path, :aadhaar_document_name, :aadhaar_document_mime,
                    :pan_document_path, :pan_document_name, :pan_document_mime, 'submitted', CURRENT_TIMESTAMP
                 )
                 ON DUPLICATE KEY UPDATE
                    full_name = VALUES(full_name), email = VALUES(email), mobile = VALUES(mobile),
                    bank_name = VALUES(bank_name), account_holder_name = VALUES(account_holder_name),
                    account_number_encrypted = VALUES(account_number_encrypted), ifsc_code = VALUES(ifsc_code),
                    branch_name = VALUES(branch_name), aadhaar_number_encrypted = VALUES(aadhaar_number_encrypted),
                    pan_number_encrypted = VALUES(pan_number_encrypted),
                    aadhaar_document_path = VALUES(aadhaar_document_path),
                    aadhaar_document_name = VALUES(aadhaar_document_name),
                    aadhaar_document_mime = VALUES(aadhaar_document_mime),
                    pan_document_path = VALUES(pan_document_path),
                    pan_document_name = VALUES(pan_document_name),
                    pan_document_mime = VALUES(pan_document_mime),
                    status = 'submitted', submitted_at = CURRENT_TIMESTAMP"
            );
            $statement->execute([
                'user_id' => $userId,
                'full_name' => $profile['fullName'],
                'email' => $profile['email'],
                'mobile' => $profile['mobile'],
                'bank_name' => $bank['bankName'],
                'account_holder_name' => $bank['accountHolderName'],
                'account_number_encrypted' => $this->encrypt($bank['accountNumber']),
                'ifsc_code' => $bank['ifscCode'],
                'branch_name' => $bank['branchName'],
                'aadhaar_number_encrypted' => $this->encrypt($identity['aadhaarNumber']),
                'pan_number_encrypted' => $this->encrypt($identity['panNumber']),
                'aadhaar_document_path' => $documentRecords['aadhaar']['path'],
                'aadhaar_document_name' => $documentRecords['aadhaar']['name'],
                'aadhaar_document_mime' => $documentRecords['aadhaar']['mimeType'],
                'pan_document_path' => $documentRecords['pan']['path'],
                'pan_document_name' => $documentRecords['pan']['name'],
                'pan_document_mime' => $documentRecords['pan']['mimeType'],
            ]);
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            foreach ($savedPaths as $path) {
                if (is_file($path) && !unlink($path)) {
                    error_log('Could not remove a KYC document after its database update failed.');
                }
            }
            throw $error;
        }

        foreach (['aadhaar', 'pan'] as $type) {
            $previousPath = $current[$type]['path'] ?? null;
            if (is_string($previousPath) && $previousPath !== $documentRecords[$type]['path']) {
                $path = $storageDirectory . DIRECTORY_SEPARATOR . $previousPath;
                if (is_file($path) && !unlink($path)) {
                    error_log(sprintf('Could not remove a replaced %s KYC document.', $type));
                }
            }
        }

        return $this->get($userId);
    }

    private function existingDocuments(int $userId): array
    {
        $statement = $this->database->prepare(
            'SELECT aadhaar_document_path, aadhaar_document_name, aadhaar_document_mime,
                    pan_document_path, pan_document_name, pan_document_mime
             FROM employee_kyc WHERE user_id = :user_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return ['aadhaar' => null, 'pan' => null];
        }
        return [
            'aadhaar' => [
                'path' => (string) $row['aadhaar_document_path'],
                'name' => (string) $row['aadhaar_document_name'],
                'mimeType' => (string) $row['aadhaar_document_mime'],
            ],
            'pan' => [
                'path' => (string) $row['pan_document_path'],
                'name' => (string) $row['pan_document_name'],
                'mimeType' => (string) $row['pan_document_mime'],
            ],
        ];
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
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
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
        $nonce = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($value, 'aes-256-gcm', $this->encryptionKey(), OPENSSL_RAW_DATA, $nonce, $tag);
        if (!is_string($cipherText) || strlen($tag) !== 16) {
            throw new RuntimeException('KYC data could not be encrypted.');
        }
        return base64_encode($nonce . $tag . $cipherText);
    }

    private function decrypt(string $value): string
    {
        $encoded = base64_decode($value, true);
        if (!is_string($encoded) || strlen($encoded) < 29) {
            throw new RuntimeException('Saved KYC data could not be verified.');
        }
        $plainText = openssl_decrypt(
            substr($encoded, 28),
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            substr($encoded, 0, 12),
            substr($encoded, 12, 16)
        );
        if (!is_string($plainText)) {
            throw new RuntimeException('Saved KYC data could not be decrypted. Check the private KYC encryption key.');
        }
        return $plainText;
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
