<?php

declare(strict_types=1);

use Arowin\EncryptionPort;
use Arowin\Modules\Employees\DocumentStoragePort;
use Arowin\Modules\Employees\EmployeeKycController;
use Arowin\Modules\Employees\KycRepository;
use PHPUnit\Framework\TestCase;
use PDO;

final class EmployeeKycControllerTest extends TestCase
{
    public function testSubmitAcceptsValidKycAndReturnsDecryptedProfile(): void
    {
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $aadhaarPath = tempnam(sys_get_temp_dir(), 'aadhaar');
        $panPath = tempnam(sys_get_temp_dir(), 'pan');
        file_put_contents($aadhaarPath, $pdf);
        file_put_contents($panPath, $pdf);

        $repository = new class implements KycRepository {
            public array $rows = [];

            public function findByUserId(int $userId): ?array
            {
                return $this->rows[$userId] ?? null;
            }

            public function findExistingDocuments(int $userId): array
            {
                $row = $this->rows[$userId] ?? null;
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
                $this->rows[$userId] = [
                    'employee_id' => 'EMP-901',
                    'name' => 'Jane Employee',
                    'email' => 'jane@example.com',
                    'mobile' => '9876543210',
                    'role' => 'telecaller',
                    'joining_date' => '2024-01-15',
                    'office_address' => 'Bengaluru',
                    'full_name' => $profile['fullName'],
                    'kyc_email' => $profile['email'],
                    'kyc_mobile' => $profile['mobile'],
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
                    'status' => 'submitted',
                    'submitted_at' => '2024-01-15 10:30:00',
                ];
                return $this->rows[$userId];
            }
        };
        $documentStorage = new class implements DocumentStoragePort {
            public array $writes = [];

            public function store(string $userId, array $file, string $type): array
            {
                $path = sprintf('%s-%s.pdf', $type, $userId);
                $this->writes[] = compact('userId', 'file', 'type', 'path');
                return [
                    'path' => $path,
                    'name' => $file['originalName'],
                    'mimeType' => $file['mimeType'],
                    'fullPath' => $path,
                ];
            }

            public function delete(string $userId, string $relativePath): void
            {
            }
        };
        $encryption = new class implements EncryptionPort {
            public function encrypt(string $value): string
            {
                return base64_encode($value);
            }

            public function decrypt(string $value): string
            {
                return base64_decode($value, true) ?: '';
            }
        };

        $controller = new EmployeeKycController(
            new PDO('sqlite::memory:'),
            $repository,
            $documentStorage,
            $encryption
        );

        $result = $controller->submit(11, [
            'fullName' => 'Jane Employee',
            'mobile' => '+91 98765 43210',
            'email' => 'jane@example.com',
            'bankName' => 'State Bank of India',
            'accountHolderName' => 'Jane Employee',
            'accountNumber' => '1234567890',
            'ifscCode' => 'SBIN0001234',
            'branchName' => 'Bengaluru',
            'aadhaarNumber' => '987654321012',
            'panNumber' => 'ABCDE1234F',
        ], [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $aadhaarPath,
            'name' => 'aadhaar.pdf',
            'size' => strlen($pdf),
        ], [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $panPath,
            'name' => 'pan.pdf',
            'size' => strlen($pdf),
        ]);

        $this->assertSame('Jane Employee', $result['profile']['fullName']);
        $this->assertSame('+91 98765 43210', $result['profile']['mobile']);
        $this->assertSame('1234567890', $result['bank']['accountNumber']);
        $this->assertSame('987654321012', $result['identity']['aadhaarNumber']);
        $this->assertSame('submitted', $result['status']);
    }

    public function testSubmitRejectsInvalidDocumentAndMalformedPayload(): void
    {
        $that = new class implements KycRepository {
            public function findByUserId(int $userId): ?array { return null; }
            public function findExistingDocuments(int $userId): array { return ['aadhaar' => null, 'pan' => null]; }
            public function saveSubmission(int $userId, array $profile, array $bank, array $identity, array $documentRecords): array { return []; }
        };
        $controller = new EmployeeKycController(new PDO('sqlite::memory:'), $that, new class implements DocumentStoragePort {
            public function store(string $userId, array $file, string $type): array { return ['path' => 'stored', 'name' => 'stored', 'mimeType' => 'application/pdf']; }
            public function delete(string $userId, string $relativePath): void {}
        }, new class implements EncryptionPort {
            public function encrypt(string $value): string { return base64_encode($value); }
            public function decrypt(string $value): string { return base64_decode($value, true) ?: ''; }
        });

        $badPath = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($badPath, 'not a real document');

        $this->expectException(\InvalidArgumentException::class);
        $controller->submit(12, [
            'fullName' => 'Jane Employee',
            'mobile' => '+91 98765 43210',
            'email' => 'jane@example.com',
            'bankName' => 'State Bank of India',
            'accountHolderName' => 'Jane Employee',
            'accountNumber' => '1234567890',
            'ifscCode' => 'SBIN0001234',
            'branchName' => 'Bengaluru',
            'aadhaarNumber' => '987654321012',
            'panNumber' => 'ABCDE1234F',
        ], [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $badPath,
            'name' => 'bad.exe',
            'size' => 17,
        ], [
            'error' => UPLOAD_ERR_NO_FILE,
        ]);
    }
}
