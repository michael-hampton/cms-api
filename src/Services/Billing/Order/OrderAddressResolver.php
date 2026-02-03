<?php

namespace App\Services\Billing\Order;

use App\Models\Member;
use App\Repositories\Members\AddressRepository;

class OrderAddressResolver
{
    public function __construct(
        private readonly AddressRepository $addressRepository
    )
    {
    }

    /**
     * Resolve shipping and billing addresses from data.
     * Mutates $data in place.
     */
    public function resolveAddresses(array &$data, ?Member $member, int $siteId): array
    {
        $this->resolveShippingAddress($data, $member, $siteId);
        $this->resolveBillingAddress($data, $member, $siteId);

        // Encode any remaining array addresses to JSON for guest orders
        if (!empty($data['billing_address']) && is_array($data['billing_address'])) {
            $data['billing_address'] = json_encode($data['billing_address']);
        }
        if (!empty($data['shipping_address']) && is_array($data['shipping_address'])) {
            $data['shipping_address'] = json_encode($data['shipping_address']);
        }

        return $data;
    }

    private function resolveShippingAddress(array &$data, ?Member $member, int $siteId): void
    {
        if (isset($data['shipping_address_id'])) {
            $this->validateAddressBelongsToMember(
                $data['shipping_address_id'],
                $member,
                'shipping'
            );
            return;
        }

        if (!isset($data['shipping_address']) || !is_array($data['shipping_address'])) {
            return;
        }

        if (empty(array_filter($data['shipping_address']))) {
            return;
        }

        // Create address for BOTH members and guests
        $addressData = $data['shipping_address'];
        $addressData['type'] = 'shipping';
        $addressData['label'] = 'Order Address';
        $addressData['is_guest'] = $member === null;

        $newAddress = $member
            ? $this->addressRepository->createAddressForMember($member->id, $addressData, $siteId)
            : $this->addressRepository->createGuestAddress($addressData, $siteId);

        $data['shipping_address_id'] = $newAddress->id;
        unset($data['shipping_address']);
    }

    private function resolveBillingAddress(array &$data, ?Member $member, int $siteId): void
    {
        if (isset($data['billing_address_id'])) {
            $this->validateAddressBelongsToMember(
                $data['billing_address_id'],
                $member,
                'billing'
            );
            return;
        }

        if (!isset($data['billing_address']) || !is_array($data['billing_address'])) {
            return;
        }

        if (empty(array_filter($data['billing_address']))) {
            return;
        }

        $addressData = $data['billing_address'];
        $addressData['type'] = 'billing';
        $addressData['label'] = 'Order Billing Address';

        $newAddress = $member
            ? $this->addressRepository->createAddressForMember($member->id, $addressData, $siteId)
            : $this->addressRepository->createGuestAddress($addressData, $siteId);

        $data['billing_address_id'] = $newAddress->id;
        unset($data['billing_address']);
    }

    private function validateAddressBelongsToMember(
        int     $addressId,
        ?Member $member,
        string  $type
    ): void
    {
        if (!$member) {
            return;
        }

        $address = $this->addressRepository->find($addressId);
        if (!$address || $address->member_id !== $member->id) {
            throw new \Exception("Invalid {$type} address");
        }
    }
}