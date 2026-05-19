<?php

namespace App\Services\Gdpr\Exporters;

use App\Models\Address;
use App\Models\Member;

final class AddressesExporter implements MemberDataExporter
{
    public function key(): string
    {
        return 'addresses';
    }

    public function export(Member $member): array
    {
        return Address::where('member_id', $member->id)
            ->get()
            ->map(fn(Address $a) => [
                'id'             => $a->id,
                'type'           => $a->type,
                'label'          => $a->label,
                'is_default'     => $a->is_default,
                'address_line_1' => $a->address_line_1,
                'address_line_2' => $a->address_line_2,
                'city'           => $a->city,
                'state'          => $a->state,
                'postcode'       => $a->postcode,
                'country'        => $a->country,
                'created_at'     => $a->created_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();
    }
}