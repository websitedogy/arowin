<?php

declare(strict_types=1);

namespace Arowin\Modules\Leads;

use Arowin\Modules\Territories\IndiaTerritories;
use DateTimeImmutable;
use PDO;

final class LeadController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function listForTelecaller(int $telecallerId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, customer_name, mobile_number, state_code, state_name, district,
                    email, address, date_of_birth, gender, note, created_at
             FROM telecaller_leads
             WHERE telecaller_id = :telecaller_id
             ORDER BY created_at DESC, id DESC
             LIMIT 1000'
        );
        $statement->execute(['telecaller_id' => $telecallerId]);

        return array_map(fn (array $row): array => $this->serializeLead($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listAllForSubEditor(): array
    {
        $statement = $this->database->query(
            "SELECT lead.id, lead.customer_name, lead.mobile_number, lead.state_code, lead.state_name,
                    lead.district, lead.email, lead.address, lead.date_of_birth, lead.gender,
                    lead.note, lead.created_at,
                    employee.employee_id, employee.name AS employee_name
             FROM telecaller_leads AS lead
             INNER JOIN users AS employee
               ON employee.id = lead.telecaller_id AND employee.role = 'telecaller'
             ORDER BY lead.created_at DESC, lead.id DESC
             LIMIT 1000"
        );

        return array_map(fn (array $row): array => $this->serializeLead($row) + [
            'employeeId' => (string) $row['employee_id'],
            'employeeName' => (string) $row['employee_name'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listForStateDistrict(string $stateCode, string $district): array
    {
        $statement = $this->database->prepare(
            'SELECT id, customer_name, mobile_number, state_code, state_name, district,
                    email, address, date_of_birth, gender, note, created_at
             FROM telecaller_leads
             WHERE state_code = :state_code AND district = :district
             ORDER BY created_at DESC, id DESC
             LIMIT 1000'
        );
        $statement->execute([
            'state_code' => $stateCode,
            'district' => $district,
        ]);

        return array_map(fn (array $row): array => $this->serializeLead($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(int $telecallerId, array $payload): array
    {
        $customerName = trim((string) ($payload['customerName'] ?? ''));
        if (
            $customerName === ''
            || strlen($customerName) > 120
            || preg_match('/[\x00-\x1F\x7F]/', $customerName) === 1
        ) {
            throw new \InvalidArgumentException('Enter a customer name (maximum 120 characters).');
        }

        $mobileNumber = trim((string) ($payload['mobileNumber'] ?? ''));
        if (
            preg_match('/^[0-9+().\-\s]{7,25}$/', $mobileNumber) !== 1
            || preg_match('/[0-9]/', $mobileNumber) !== 1
        ) {
            throw new \InvalidArgumentException('Enter a valid mobile number.');
        }

        $district = trim((string) ($payload['district'] ?? ''));
        if (
            $district === ''
            || strlen($district) > 100
            || preg_match('/[\x00-\x1F\x7F]/', $district) === 1
        ) {
            throw new \InvalidArgumentException('Enter a district (maximum 100 characters).');
        }

        $state = IndiaTerritories::findState((string) ($payload['stateCode'] ?? ''));
        if ($state === null) {
            throw new \InvalidArgumentException('Choose a valid Indian state.');
        }

        $district = IndiaTerritories::findDistrict($state['code'], $district) ?? '';
        if ($district === '') {
            throw new \InvalidArgumentException('Choose a valid district for the selected state.');
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if (strlen($email) > 180 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Enter a valid email address (maximum 180 characters).');
        }

        $address = trim((string) ($payload['address'] ?? ''));
        if (
            $address === ''
            || strlen($address) > 500
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $address) === 1
        ) {
            throw new \InvalidArgumentException('Enter an address (maximum 500 characters).');
        }

        $dateOfBirth = trim((string) ($payload['dateOfBirth'] ?? ''));
        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (
            $birthDate === false
            || $birthDate->format('Y-m-d') !== $dateOfBirth
            || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
            || $birthDate > new DateTimeImmutable('today')
        ) {
            throw new \InvalidArgumentException('Enter a valid date of birth that is not in the future.');
        }

        $gender = strtolower(trim((string) ($payload['gender'] ?? '')));
        if (!in_array($gender, ['male', 'female'], true)) {
            throw new \InvalidArgumentException('Choose Male or Female.');
        }

        $note = trim((string) ($payload['note'] ?? ''));
        if (strlen($note) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $note) === 1) {
            throw new \InvalidArgumentException('The optional note must be 500 characters or fewer.');
        }

        $statement = $this->database->prepare(
            'INSERT INTO telecaller_leads
                (telecaller_id, customer_name, mobile_number, state_code, state_name, district,
                 email, address, date_of_birth, gender, note)
             VALUES
                (:telecaller_id, :customer_name, :mobile_number, :state_code, :state_name, :district,
                 :email, :address, :date_of_birth, :gender, :note)'
        );
        $statement->execute([
            'telecaller_id' => $telecallerId,
            'customer_name' => $customerName,
            'mobile_number' => $mobileNumber,
            'state_code' => $state['code'],
            'state_name' => $state['name'],
            'district' => $district,
            'email' => $email,
            'address' => $address,
            'date_of_birth' => $dateOfBirth,
            'gender' => $gender,
            'note' => $note,
        ]);

        $id = (int) $this->database->lastInsertId();
        $created = $this->database->prepare(
            'SELECT id, customer_name, mobile_number, state_code, state_name, district,
                    email, address, date_of_birth, gender, note, created_at
             FROM telecaller_leads WHERE id = :id'
        );
        $created->execute(['id' => $id]);
        $row = $created->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \RuntimeException('The lead was saved but could not be loaded.');
        }

        return $this->serializeLead($row);
    }

    private function serializeLead(array $row): array
    {
        $id = (int) $row['id'];
        $dateOfBirth = is_string($row['date_of_birth'] ?? null) ? $row['date_of_birth'] : null;
        $age = $this->ageFromDateOfBirth($dateOfBirth);

        return [
            'id' => $id,
            'leadId' => 'LD-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            'customerName' => (string) $row['customer_name'],
            'mobileNumber' => (string) $row['mobile_number'],
            'stateCode' => (string) ($row['state_code'] ?? ''),
            'stateName' => (string) ($row['state_name'] ?? ''),
            'district' => (string) $row['district'],
            'email' => (string) ($row['email'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'age' => $age,
            'gender' => (string) ($row['gender'] ?? ''),
            'note' => (string) $row['note'],
            'createdAt' => (string) $row['created_at'],
        ];
    }

    private function ageFromDateOfBirth(?string $dateOfBirth): ?int
    {
        if ($dateOfBirth === null || $dateOfBirth === '') {
            return null;
        }

        $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
        if ($birthDate === false || $birthDate->format('Y-m-d') !== $dateOfBirth) {
            return null;
        }

        return $birthDate->diff(new DateTimeImmutable('today'))->y;
    }
}
