<?php

declare(strict_types=1);

namespace Arowin\Modules\Employees;

use PDO;

final class PdoKycRepository implements KycRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function findByUserId(int $userId): ?array
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
        return is_array($row) ? $row : null;
    }

    public function findExistingDocuments(int $userId): array
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
                'path' => (string) ($row['aadhaar_document_path'] ?? ''),
                'name' => (string) ($row['aadhaar_document_name'] ?? ''),
                'mimeType' => (string) ($row['aadhaar_document_mime'] ?? ''),
            ],
            'pan' => [
                'path' => (string) ($row['pan_document_path'] ?? ''),
                'name' => (string) ($row['pan_document_name'] ?? ''),
                'mimeType' => (string) ($row['pan_document_mime'] ?? ''),
            ],
        ];
    }

    public function saveSubmission(int $userId, array $profile, array $bank, array $identity, array $documentRecords): array
    {
        $this->database->beginTransaction();
        try {
            $query = $this->database->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
                ? "INSERT INTO employee_kyc (
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
                : "INSERT INTO employee_kyc (
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
                 ON CONFLICT(user_id) DO UPDATE SET
                    full_name = excluded.full_name, email = excluded.email, mobile = excluded.mobile,
                    bank_name = excluded.bank_name, account_holder_name = excluded.account_holder_name,
                    account_number_encrypted = excluded.account_number_encrypted, ifsc_code = excluded.ifsc_code,
                    branch_name = excluded.branch_name, aadhaar_number_encrypted = excluded.aadhaar_number_encrypted,
                    pan_number_encrypted = excluded.pan_number_encrypted,
                    aadhaar_document_path = excluded.aadhaar_document_path,
                    aadhaar_document_name = excluded.aadhaar_document_name,
                    aadhaar_document_mime = excluded.aadhaar_document_mime,
                    pan_document_path = excluded.pan_document_path,
                    pan_document_name = excluded.pan_document_name,
                    pan_document_mime = excluded.pan_document_mime,
                    status = 'submitted', submitted_at = CURRENT_TIMESTAMP";

            $statement = $this->database->prepare($query);
            $statement->execute([
                'user_id' => $userId,
                'full_name' => $profile['fullName'],
                'email' => $profile['email'],
                'mobile' => $profile['mobile'],
                'bank_name' => $bank['bankName'],
                'account_holder_name' => $bank['accountHolderName'],
                'account_number_encrypted' => $bank['accountNumberEncrypted'],
                'ifsc_code' => $bank['ifscCode'],
                'branch_name' => $bank['branchName'],
                'aadhaar_number_encrypted' => $identity['aadhaarEncrypted'],
                'pan_number_encrypted' => $identity['panEncrypted'],
                'aadhaar_document_path' => $documentRecords['aadhaar']['path'],
                'aadhaar_document_name' => $documentRecords['aadhaar']['name'],
                'aadhaar_document_mime' => $documentRecords['aadhaar']['mimeType'],
                'pan_document_path' => $documentRecords['pan']['path'],
                'pan_document_name' => $documentRecords['pan']['name'],
                'pan_document_mime' => $documentRecords['pan']['mimeType'],
            ]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return $this->findByUserId($userId) ?? [];
    }
}
