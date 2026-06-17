<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

use InvalidArgumentException;

final readonly class BulkSubscriptionImportRow
{
    public function __construct(
        public string $email,
        public string $firstName,
        public string $lastName,
        public int $planId,
        public string $paymentMethodId,
        public array $address,
        public ?int $pricingTierId = null,
        public ?string $offerType = null,
    ) {
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email is required.');
        }
        if ($this->firstName === '' || $this->lastName === '') {
            throw new InvalidArgumentException('First name and last name are required.');
        }
        if ($this->planId < 1) {
            throw new InvalidArgumentException('A valid plan_id is required.');
        }
        if ($this->paymentMethodId === '') {
            throw new InvalidArgumentException('payment_method_id is required.');
        }
    }

    public static function fromArray(array $row): self
    {
        foreach (['address_line_1', 'city', 'postcode', 'country_code'] as $field) {
            if (trim((string)($row[$field] ?? '')) === '') {
                throw new InvalidArgumentException("{$field} is required.");
            }
        }

        return new self(
            email: strtolower(trim((string)($row['email'] ?? ''))),
            firstName: trim((string)($row['first_name'] ?? '')),
            lastName: trim((string)($row['last_name'] ?? '')),
            planId: (int)($row['plan_id'] ?? 0),
            paymentMethodId: trim((string)($row['payment_method_id'] ?? '')),
            address: array_filter([
                'first_name' => trim((string)($row['first_name'] ?? '')),
                'last_name' => trim((string)($row['last_name'] ?? '')),
                'address_line_1' => trim((string)($row['address_line_1'] ?? '')),
                'address_line_2' => trim((string)($row['address_line_2'] ?? '')),
                'city' => trim((string)($row['city'] ?? '')),
                'county' => trim((string)($row['county'] ?? '')),
                'postcode' => trim((string)($row['postcode'] ?? '')),
                'country_code' => strtoupper(trim((string)($row['country_code'] ?? ''))),
                'phone' => trim((string)($row['phone'] ?? '')),
                'type' => 'both',
                'is_default' => true,
            ], static fn(mixed $value): bool => $value !== '' && $value !== null),
            pricingTierId: ($row['pricing_tier_id'] ?? '') !== '' ? (int)$row['pricing_tier_id'] : null,
            offerType: ($row['offer_type'] ?? '') !== '' ? trim((string)$row['offer_type']) : null,
        );
    }
}
