<?php

declare(strict_types=1);

namespace Arowin\Modules\Leads;

use PDO;

final class LeadController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function listForTelecaller(int $telecallerId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, customer_name, mobile_number, district, note, created_at
             FROM telecaller_leads
             WHERE telecaller_id = :telecaller_id
             ORDER BY created_at DESC, id DESC
             LIMIT 1000'
        );
        $statement->execute(['telecaller_id' => $telecallerId]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'customerName' => (string) $row['customer_name'],
            'mobileNumber' => (string) $row['mobile_number'],
            'district' => (string) $row['district'],
            'note' => (string) $row['note'],
            'createdAt' => (string) $row['created_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
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

        $note = trim((string) ($payload['note'] ?? ''));
        if (strlen($note) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $note) === 1) {
            throw new \InvalidArgumentException('The optional note must be 500 characters or fewer.');
        }

        $statement = $this->database->prepare(
            'INSERT INTO telecaller_leads
                (telecaller_id, customer_name, mobile_number, district, note)
             VALUES
                (:telecaller_id, :customer_name, :mobile_number, :district, :note)'
        );
        $statement->execute([
            'telecaller_id' => $telecallerId,
            'customer_name' => $customerName,
            'mobile_number' => $mobileNumber,
            'district' => $district,
            'note' => $note,
        ]);

        $id = (int) $this->database->lastInsertId();
        $created = $this->database->prepare(
            'SELECT id, customer_name, mobile_number, district, note, created_at
             FROM telecaller_leads WHERE id = :id'
        );
        $created->execute(['id' => $id]);
        $row = $created->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \RuntimeException('The lead was saved but could not be loaded.');
        }

        return [
            'id' => (int) $row['id'],
            'customerName' => (string) $row['customer_name'],
            'mobileNumber' => (string) $row['mobile_number'],
            'district' => (string) $row['district'],
            'note' => (string) $row['note'],
            'createdAt' => (string) $row['created_at'],
        ];
    }
}
