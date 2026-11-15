<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

use PDO;

final class GlobalCustomerLookup
{
    private const SUPPORTED_PACKAGES = ['50', '150', '350', '750', '1550', '3150', '6350', '12750'];

    public function __construct(private readonly PDO $database)
    {
    }

    public function lookupCustomer(string $customerId): array
    {
        $customerId = trim($customerId);
        if ($customerId === '' || strlen($customerId) > 60) {
            throw new \InvalidArgumentException('Enter a valid Arowin Global customer ID.');
        }

        $statement = $this->database->prepare(
            "SELECT username, full_name, purchased_package, node_package, status
             FROM node_registrations
             WHERE username = :customer_id
             LIMIT 1"
        );
        $statement->execute(['customer_id' => $customerId]);
        $customer = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($customer)) {
            throw new \DomainException('Customer ID was not found in Arowin Global.');
        }

        $status = (string) $customer['status'];
        if ($status === 'rejected') {
            throw new \DomainException('This customer ID is not active in Arowin Global.');
        }

        if ($status === 'pending') {
            $packageAmount = $this->formatPackage($customer['node_package']);
            return [
                'customerId' => (string) $customer['username'],
                'customerName' => (string) $customer['full_name'],
                'packageAmount' => $packageAmount,
                'activationStatus' => 'pending',
                'isEmployeeCredited' => false,
                'canSubmit' => $packageAmount !== null,
                'message' => $packageAmount === null
                    ? 'This customer ID is pending activation and has no package selected. Ask the customer to purchase a package. OTP can be sent, but no sale can be submitted without a package.'
                    : 'This customer ID is pending activation. Ask the customer to purchase the selected package. This request can be submitted for review, but it will not count toward employee sales or incentives.',
            ];
        }

        if ($status !== 'active') {
            throw new \DomainException('This customer ID is not active in Arowin Global.');
        }

        if ($customer['purchased_package'] === null) {
            return [
                'customerId' => (string) $customer['username'],
                'customerName' => (string) $customer['full_name'],
                'packageAmount' => null,
                'activationStatus' => 'active',
                'isEmployeeCredited' => false,
                'canSubmit' => false,
                'message' => 'This customer ID has no purchased package. Ask the customer to purchase a package before submitting a sale.',
            ];
        }

        $packageAmount = $this->formatPackage($customer['purchased_package']);

        return [
            'customerId' => (string) $customer['username'],
            'customerName' => (string) $customer['full_name'],
            'packageAmount' => $packageAmount,
            'activationStatus' => 'active',
            'isEmployeeCredited' => true,
            'canSubmit' => true,
            'message' => 'Customer details verified from Arowin Global.',
        ];
    }

    private function formatPackage(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $packageAmount = (string) $value;
        if (!in_array($packageAmount, self::SUPPORTED_PACKAGES, true)) {
            throw new \RuntimeException('The customer has an unsupported Arowin Global package.');
        }

        return number_format((float) $packageAmount, 2, '.', '');
    }
}
