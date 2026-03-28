<?php

declare(strict_types=1);

namespace App\Services\Product\Fulfilment;

use App\Models\Order;
use App\Repositories\Members\AddressRepository;

/**
 * Resolves the delivery address for a product order.
 *
 * Parallel to PrintAddressResolver in the print pipeline.
 * PrintAddressResolver is closed for modification — this is a new class
 * with the same contract but operating on Order rather than Subscription.
 *
 * Priority:
 *   1. Order shipping_address (set at checkout).
 *   2. Order billing_address as fallback.
 *   3. Throws if neither is usable — a missing address is a hard failure
 *      for physical fulfilment.
 *
 * Single reason to change: the address selection rules for orders.
 */
class ProductAddressResolver
{
    public function __construct(private readonly AddressRepository $addressRepository)
    {

    }

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
    public function resolve(Order $order): array
    {
        $address = $this->pickAddress($order);

        if (!$address) {
            throw new \RuntimeException(
                "Cannot fulfil order #{$order->id}: no valid delivery address found"
            );
        }

        $this->guardRequiredFields($address, $order->id);

        return [
            'full_name' => trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? '')),
            'address_line_1' => $address['address_line_1'],
            'address_line_2' => $address['address_line_2'] ?? null,
            'city' => $address['city'],
            'postcode' => $address['postcode'],
            'country' => $address['country'],
            'snapshot' => $address,
        ];
    }

    private function pickAddress(Order $order): ?array
    {
        $addresses = $this->addressRepository->getAddressesForMember($order->member_id);

        $normalized = array_map(function ($address) {
            return is_array($address) ? $address : $address->toArray();
        }, $addresses->toArray());

        // 1. Try shipping first
        foreach ($normalized as $address) {
            if (($address['type'] ?? null) === 'shipping' && $this->isUsableAddress($address)) {
                return $address;
            }
        }

        // 2. Then billing
        foreach ($normalized as $address) {
            if (($address['type'] ?? null) === 'billing' && $this->isUsableAddress($address)) {
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

        return !empty($address['address_line_1'])
            && !empty($address['city'])
            && !empty($address['postcode'])
            && !empty($address['country']);
    }

    private function guardRequiredFields(array $address, int $orderId): void
    {
        foreach (['address_line_1', 'city', 'postcode', 'country'] as $field) {
            if (empty($address[$field])) {
                throw new \RuntimeException(
                    "Product delivery address for order #{$orderId} is missing required field: {$field}"
                );
            }
        }
    }
}