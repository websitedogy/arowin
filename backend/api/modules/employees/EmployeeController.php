<?php

declare(strict_types=1);

namespace Arowin\Modules\Employees;

use Arowin\EmployeeRoles;
use Arowin\Modules\Credentials\CredentialDeliveryService;
use PDO;
use PDOException;

final class EmployeeController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function createEmployee(array $payload, int $creatorId): array
    {
        $name = trim((string) ($payload['fullName'] ?? ''));
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $mobile = trim((string) ($payload['mobile'] ?? ''));
        $dateOfBirth = trim((string) ($payload['dob'] ?? ''));
        $address = trim((string) ($payload['address'] ?? ''));
        $role = EmployeeRoles::normalize((string) ($payload['role'] ?? ''));

        if ($name === '' || $email === '' || $password === '' || $mobile === '' || $dateOfBirth === '' || $address === '' || $role === null) {
            throw new \InvalidArgumentException('All employee fields and a valid role are required.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid email address.');
        }

        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
        if (!$date || $date->format('Y-m-d') !== $dateOfBirth) {
            throw new \InvalidArgumentException('Enter a valid date of birth.');
        }

        $credentialDelivery = new CredentialDeliveryService($this->database);
        $employeeCode = '';
        $employeeId = 0;
        $activityId = 0;
        $activityTime = '';
        $createdAt = '';
        $credentialLink = [];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $employeeCode = EmployeeIdGenerator::generate($this->database);
            $this->database->beginTransaction();

            try {
                $statement = $this->database->prepare(
                    'INSERT INTO users (employee_id, name, email, password_hash, role, mobile, date_of_birth, address, is_active)
                     VALUES (:employee_id, :name, :email, :password_hash, :role, :mobile, :date_of_birth, :address, 1)'
                );
                $statement->execute([
                    'employee_id' => $employeeCode,
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                    'mobile' => $mobile,
                    'date_of_birth' => $dateOfBirth,
                    'address' => $address,
                ]);
                $employeeId = (int) $this->database->lastInsertId();
                $createdAtQuery = $this->database->prepare('SELECT created_at FROM users WHERE id = :id');
                $createdAtQuery->execute(['id' => $employeeId]);
                $createdAt = (string) $createdAtQuery->fetchColumn();
                $credentialLink = $credentialDelivery->prepare($employeeId, $password);

                $activity = $this->database->prepare(
                    "INSERT INTO activity_logs (user_id, action, metadata)
                     VALUES (:user_id, 'employee.created', :metadata)"
                );
                $activity->execute([
                    'user_id' => $creatorId,
                    'metadata' => json_encode([
                        'employee_id' => $employeeCode,
                        'user_id' => $employeeId,
                        'employee_name' => $name,
                        'role' => $role,
                    ], JSON_THROW_ON_ERROR),
                ]);
                $activityId = (int) $this->database->lastInsertId();
                $activityTimeQuery = $this->database->prepare('SELECT created_at FROM activity_logs WHERE id = :id');
                $activityTimeQuery->execute(['id' => $activityId]);
                $activityTime = (string) $activityTimeQuery->fetchColumn();
                $this->database->commit();
                break;
            } catch (PDOException $error) {
                if ($this->database->inTransaction()) {
                    $this->database->rollBack();
                }

                if (str_contains($error->getMessage(), 'uq_users_employee_id')) {
                    if ($attempt < 4) {
                        continue;
                    }

                    throw new \RuntimeException('Could not allocate a unique 6-digit Employee ID.', 0, $error);
                }

                if ($error->getCode() === '23000') {
                    throw new \DomainException('An account with this email already exists.');
                }

                throw $error;
            } catch (\Throwable $error) {
                if ($this->database->inTransaction()) {
                    $this->database->rollBack();
                }

                throw $error;
            }
        }

        $emailSent = $this->sendCredentials(
            $credentialDelivery,
            $email,
            $name,
            EmployeeRoles::label($role),
            $credentialLink
        );
        $emailDelivery = [
            'sent' => $emailSent,
            'message' => $emailSent
                ? 'Credentials download link sent.'
                : 'SMTP could not send the credentials email. Check SMTP configuration and retry.',
        ];

        return [
            'employee' => [
                'id' => $employeeId,
                'employeeId' => $employeeCode,
                'fullName' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'dob' => $dateOfBirth,
                'address' => $address,
                'joiningDate' => null,
                'role' => EmployeeRoles::label($role),
                'roleKey' => $role,
                'status' => 'Active',
                'createdAt' => $createdAt,
                'dashboard' => $role,
            ],
            'activity' => [
                'id' => $activityId,
                'name' => $name,
                'role' => EmployeeRoles::label($role),
                'time' => $activityTime,
            ],
            'emailDelivery' => $emailDelivery,
        ];
    }

    public function resendCredentials(int $userId): array
    {
        $credentialDelivery = new CredentialDeliveryService($this->database);
        $link = $credentialDelivery->resend($userId);
        $sent = $this->sendCredentials(
            $credentialDelivery,
            $link['email'],
            $link['name'],
            EmployeeRoles::label((string) $link['role']),
            $link
        );

        return [
            'sent' => $sent,
            'message' => $sent
                ? 'A new credentials download link was sent.'
                : 'The account exists, but email delivery failed. Check SMTP configuration and try again.',
        ];
    }

    private function sendCredentials(
        CredentialDeliveryService $delivery,
        string $email,
        string $name,
        string $role,
        array $link
    ): bool {
        try {
            $delivery->sendLink($email, $name, $role, $link['url'], $link['expiresAt']);
            $delivery->markEmailSent($link['token']);
            return true;
        } catch (\Throwable $error) {
            error_log(sprintf('AROWIN credential email to %s failed: %s', $email, $error->getMessage()));
            return false;
        }
    }

    public function listEmployees(): array
    {
        $statement = $this->database->query(
            'SELECT employee.id, employee.employee_id, employee.name, employee.email, employee.role, employee.mobile,
                    employee.date_of_birth, employee.address, salary.joining_date, employee.is_active, employee.created_at
             FROM users AS employee
             LEFT JOIN employee_salaries AS salary ON salary.user_id = employee.id
             ORDER BY employee.created_at DESC, employee.id DESC'
        );

        return array_map(static function (array $row): array {
            $role = (string) $row['role'];

            return [
                'id' => (int) $row['id'],
                'employeeId' => (string) $row['employee_id'],
                'fullName' => $row['name'],
                'email' => $row['email'],
                'mobile' => $row['mobile'],
                'dob' => $row['date_of_birth'],
                'address' => $row['address'],
                'joiningDate' => $row['joining_date'],
                'role' => EmployeeRoles::label($role),
                'roleKey' => $role,
                'status' => (int) $row['is_active'] === 1 ? 'Active' : 'Inactive',
                'createdAt' => $row['created_at'],
                'dashboard' => $role,
            ];
        }, $statement->fetchAll());
    }

    public function listRecentActivity(int $limit = 10): array
    {
        $statement = $this->database->prepare(
            "SELECT id, metadata, created_at
             FROM activity_logs
             WHERE action = 'employee.created'
             ORDER BY created_at DESC, id DESC
             LIMIT :limit"
        );
        $statement->bindValue('limit', max(1, min($limit, 50)), PDO::PARAM_INT);
        $statement->execute();

        return array_map(static function (array $row): array {
            $metadata = json_decode((string) $row['metadata'], true);
            $role = is_array($metadata) ? (string) ($metadata['role'] ?? '') : '';

            return [
                'id' => (int) $row['id'],
                'name' => is_array($metadata) ? (string) ($metadata['employee_name'] ?? 'Employee') : 'Employee',
                'role' => EmployeeRoles::label($role),
                'time' => $row['created_at'],
            ];
        }, $statement->fetchAll());
    }

}
