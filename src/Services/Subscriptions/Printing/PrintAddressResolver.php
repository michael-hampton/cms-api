<?php

namespace App\Services\Subscriptions\Printing;

use App\Models\Subscription;

class PrintAddressResolver
{
    public function resolve(Subscription $subscription): array
    {
        $address = $this->pickAddress($subscription);

        if (!$address) {
            throw new \RuntimeException(
                "Cannot fulfil print subscription #{$subscription->id}: no valid delivery address found"
            );
        }

        $this->guardRequiredFields($address, $subscription->id);

        return [
            'full_name' => trim(($subscription->member?->first_name) . ' ' . ($subscription->member?->last_name)),
            'address_line_1' => $address['address_line_1'],
            'address_line_2' => $address['address_line_2'] ?? null,
            'city' => $address['city'],
            'postcode' => $address['postcode'],
            'country' => $address['country'],
            'snapshot' => $address,
        ];
    }

    private function pickAddress(Subscription $subscription): ?array
    {
        $addresses = $subscription->member?->addresses;

        // Normalize everything up front
        $normalized = $addresses
            ->map(fn($addr) => $this->normalize($addr))
            ->filter(); // remove nulls

        // Prefer delivery/shipping
        $delivery = $normalized->first(function ($address) {
            return ($address['type'] ?? null) === 'delivery'
                || ($address['type'] ?? null) === 'shipping';
        });

        if ($this->isUsableAddress($delivery)) {
            return $delivery;
        }

        // Fallback to billing
        $billing = $normalized->first(function ($address) {
            return ($address['type'] ?? null) === 'billing';
        });

        if ($this->isUsableAddress($billing)) {
            return $billing;
        }

        return null;
    }

    private function isUsableAddress(mixed $address): bool
    {
        if (is_null($address)) {
            return false;
        }

        // Handle Eloquent model or array
        $address = $this->normalize($address);

        return isset($address['address_line_1']) && $address['address_line_1'] !== ''
            && isset($address['city']) && $address['city'] !== ''
            && isset($address['postcode']) && $address['postcode'] !== ''
            && isset($address['country']) && $address['country'] !== '';
    }

    private function normalize(mixed $address): ?array
    {
        if (is_array($address)) {
            return $address;
        }

        // Eloquent model → array
        if (is_object($address) && method_exists($address, 'toArray')) {
            return $address->toArray();
        }

        // JSON string fallback (just in case legacy data exists)
        if (is_string($address)) {
            return json_decode($address, true);
        }

        return null;
    }

    private function guardRequiredFields(array $address, int $subscriptionId): void
    {
        foreach (['address_line_1', 'city', 'postcode', 'country'] as $field) {
            if (empty($address[$field])) {
                throw new \RuntimeException(
                    "Print address for subscription #{$subscriptionId} is missing required field: {$field}"
                );
            }
        }
    }
}