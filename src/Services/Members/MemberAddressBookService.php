<?php

namespace App\Services\Members;

use App\Models\Address;
use App\Models\Member;
use App\Repositories\Members\AddressRepository;
use RuntimeException;

class MemberAddressBookService
{
    public function __construct(
        private readonly AddressRepository $addressRepository,
    ) {
    }

    public function list(int $memberId): array
    {
        return $this->addressRepository
            ->getAddressesForMember($memberId)
            ->toArray();
    }

    public function create(Member $member, array $data, ?int $siteId = null): Address
    {
        $data = $this->cleanAddressData($data);
        $data['member_id'] = (int) $member->id;
        $data['site_id'] = $siteId;

        $type = $data['type'] ?? 'both';
        $existingCount = Address::where('member_id', (int) $member->id)
            ->where('type', $type)
            ->count();

        $wantsDefault = !empty($data['is_default']);
        $data['is_default'] = $existingCount === 0 || $wantsDefault;

        if ($data['is_default']) {
            $this->clearDefaults((int) $member->id, $type);
        }

        return $this->addressRepository->create($data);
    }

    public function update(Member $member, int $addressId, array $data): Address
    {
        $address = $this->ownedAddress($member, $addressId);
        $data = $this->cleanAddressData($data, partial: true);

        if (empty($data)) {
            return $address;
        }

        $newType = $data['type'] ?? $address->type ?? 'both';
        $wantsDefault = array_key_exists('is_default', $data) && !empty($data['is_default']);

        if ($wantsDefault) {
            $this->clearDefaults((int) $member->id, $newType);
            $data['is_default'] = true;
        }

        $updated = $this->addressRepository->update($addressId, $data);

        if (!$updated) {
            throw new RuntimeException('Address could not be updated.');
        }

        return $this->ownedAddress($member, $addressId);
    }

    public function delete(Member $member, int $addressId): void
    {
        $address = $this->ownedAddress($member, $addressId);
        $address->delete();
    }

    public function setDefault(Member $member, int $addressId): Address
    {
        $address = $this->ownedAddress($member, $addressId);
        $this->clearDefaults((int) $member->id, $address->type ?? 'both');

        $updated = $this->addressRepository->update($addressId, ['is_default' => true]);

        if (!$updated) {
            throw new RuntimeException('Default address could not be updated.');
        }

        return $this->ownedAddress($member, $addressId);
    }

    public function ownedAddress(Member $member, int $addressId): Address
    {
        $address = $this->addressRepository->find($addressId);

        if (!$address || (int) $address->member_id !== (int) $member->id) {
            throw new RuntimeException('Address not found.');
        }

        return $address;
    }

    private function clearDefaults(int $memberId, string $type): void
    {
        Address::where('member_id', $memberId)
            ->where('type', $type)
            ->update(['is_default' => 0]);
    }

    private function cleanAddressData(array $data, bool $partial = false): array
    {
        $allowed = [
            'type',
            'label',
            'is_default',
            'address_line_1',
            'address_line_2',
            'city',
            'state',
            'postcode',
            'country',
        ];

        $clean = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];
            if (is_string($value)) {
                $value = trim($value);
            }

            $clean[$key] = $value;
        }

        if (!$partial) {
            $clean['type'] = $clean['type'] ?? 'both';
            $clean['label'] = $clean['label'] ?? null;
            $clean['address_line_2'] = $clean['address_line_2'] ?? null;
            $clean['state'] = $clean['state'] ?? null;
            $clean['is_default'] = !empty($clean['is_default']);
        } elseif (array_key_exists('is_default', $clean)) {
            $clean['is_default'] = !empty($clean['is_default']);
        }

        return $clean;
    }
}
