<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Address;

class AddressRepository extends Repository
{
    protected function getModelClass(): string
    {
        return Address::class;
    }

    public function getAddressesForMember(int $memberId): Collection
    {
        $query = Address::where('member_id', $memberId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc');


        return $this->applySiteFilter($query)->get();
    }

    public function getShippingAddressesForMember(int $memberId): Collection
    {
        return Address::where('member_id', $memberId)
            ->whereIn('type', ['shipping', 'both'])
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getBillingAddressesForMember(int $memberId): Collection
    {
        return Address::where('member_id', $memberId)
            ->whereIn('type', ['billing', 'both'])
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getDefaultShippingAddress(int $memberId): ?Address
    {
        return Address::where('member_id', $memberId)
            ->where('is_default', true)
            ->whereIn('type', ['shipping', 'both'])
            ->first();
    }

    public function getDefaultBillingAddress(int $memberId): ?Address
    {
        return Address::where('member_id', $memberId)
            ->where('is_default', true)
            ->whereIn('type', ['billing', 'both'])
            ->first();
    }

    public function setDefaultAddress(int $addressId, int $memberId): bool
    {
        $address = $this->find($addressId);

        if (!$address || $address->member_id !== $memberId) {
            return false;
        }

        // Unset other defaults
        Address::where('member_id', $memberId)
            ->where('type', $address->type)
            ->update(['is_default' => 0]);

        return $this->update($addressId, ['is_default' => true]) !== null;
    }

    public function createAddressForMember(int $memberId, array $data): Address
    {
        $data['member_id'] = $memberId;

        // If this is the first address, make it default
        $existingCount = Address::where('member_id', $memberId)
            ->where('type', $data['type'] ?? 'both')
            ->count();

        if ($existingCount === 0) {
            $data['is_default'] = true;
        } else {
            $data['is_default'] = false;
        }

        return $this->create($data);
    }
}