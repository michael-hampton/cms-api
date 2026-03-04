<?php

namespace App\Services\Subscriptions\Printing;

use App\Models\Subscription;

/**
 * Resolves the delivery address for a print subscription.
 *
 * Rule: use delivery_address if present, fall back to billing_address.
 * Throws if neither is available — a missing address is a hard failure for
 * physical fulfilment.
 */
class PrintAddressResolver
{
    /**
     * @return array{
     *     full_name: string,
     *     address_line_1: string,
     *     address_line_2: string|null,
     *     city: string,
     *     postcode: string,
     *     country: string,
     *     snapshot: array
     * }
     *
     * @throws \RuntimeException When no valid delivery address exists.
     */
    public function resolve(Subscription $subscription): array
    {
        $address = $this->pickAddress($subscription);

        if (!$address) {
            throw new \RuntimeException(
                "Cannot fulfil print subscription #{$subscription->id}: no valid delivery address found"
            );
        }

        $this->guardRequiredFields($address, $subscription->id);

        $snapshot = $address;

        return [
            'full_name' => trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? '')),
            'address_line_1' => $address['address_line_1'],
            'address_line_2' => $address['address_line_2'] ?? null,
            'city' => $address['city'],
            'postcode' => $address['postcode'],
            'country' => $address['country'],
            'snapshot' => $snapshot,
        ];
    }

    private function pickAddress(Subscription $subscription): ?array
    {
        $delivery = $subscription->delivery_address;
        if ($delivery !== null && $delivery !== '' && $delivery !== []) {
            $address = is_array($delivery)
                ? $delivery
                : json_decode($delivery, true);

            if ($this->isUsableAddress($address)) {
                return $address;
            }
        }

        $billing = $subscription->billing_address;
        if ($billing !== null && $billing !== '' && $billing !== []) {
            $address = is_array($billing)
                ? $billing
                : json_decode($billing, true);

            if ($this->isUsableAddress($address)) {
                return $address;
            }
        }

        return null;
    }

    private function isUsableAddress(?array $address): bool
    {
        if (!is_array($address)) {
            return false;
        }

        return isset($address['address_line_1']) && $address['address_line_1'] !== ''
            && isset($address['city']) && $address['city'] !== ''
            && isset($address['postcode']) && $address['postcode'] !== ''
            && isset($address['country']) && $address['country'] !== '';
    }

    private function guardRequiredFields(array $address, int $subscriptionId): void
    {
        $required = ['address_line_1', 'city', 'postcode', 'country'];

        foreach ($required as $field) {
            if (!isset($address[$field]) || $address[$field] === '') {
                throw new \RuntimeException(
                    "Print address for subscription #{$subscriptionId} is missing required field: {$field}"
                );
            }
        }
    }
}