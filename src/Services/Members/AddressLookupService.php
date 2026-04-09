<?php

namespace App\Services\Members;

use App\Repositories\Members\AddressLookupRepository;

class AddressLookupService implements AddressLookupServiceInterface
{
    public function __construct(
        private readonly AddressLookupRepository $repository
    )
    {
    }

    public function lookup(string $postcode): array
    {
        // Normalize (belt + braces, controller should already trim)
        $postcode = strtoupper(str_replace(' ', '', $postcode));

        $results = $this->repository->lookup($postcode);

        if (empty($results)) {
            return [];
        }

        $results = is_array(reset($results) ?? null) ? $results : [$results];

        return collect($results)
            ->map(fn($item) => $this->mapAddress($item))
            ->filter() // removes nulls if mapping fails
            ->values()
            ->toArray();
    }

    private function mapAddress(array $item): ?array
    {
        $address = $item['bua'] ?? $item['admin_district'] ?? '';
        $city = $item['parish'] ?? $item['admin_district'] ?? '';
        $postcode = $item['postcode'] ?? '';

        if (empty($address) && empty($city) && empty($postcode)) {
            return null;
        }

        // Map country names to codes
        $countryMap = [
            'UNITED KINGDOM' => 'GB',
            'ENGLAND' => 'GB',
            'SCOTLAND' => 'GB',
            'WALES' => 'GB',
            'NORTHERN IRELAND' => 'GB',
            'UNITED STATES' => 'US',
            'AUSTRALIA' => 'AU',
            'CANADA' => 'CA',
            'IRELAND' => 'IE',
            'NEW ZEALAND' => 'NZ',
            'SOUTH AFRICA' => 'ZA',
            'GERMANY' => 'DE',
            'FRANCE' => 'FR',
            'SPAIN' => 'ES',
            'ITALY' => 'IT',
            'NETHERLANDS' => 'NL',
            'BELGIUM' => 'BE',
            'SWEDEN' => 'SE',
            'NORWAY' => 'NO',
            'DENMARK' => 'DK',
            'FINLAND' => 'FI',
            'PORTUGAL' => 'PT',
            'AUSTRIA' => 'AT',
            'SWITZERLAND' => 'CH',
        ];

        $rawCountry = strtoupper($item['country'] ?? '');
        $country = in_array($rawCountry, ['ENGLAND', 'SCOTLAND', 'WALES', 'NORTHERN IRELAND'])
            ? 'GB'
            : ($countryMap[$rawCountry] ?? 'GB');

        return [
            'address' => $address,
            'city' => $city,
            'state' => $item['parish'] ?? $item['admin_county'] ?? '',
            'postal_code' => $postcode,
            'country' => $country ?? 'GB',
        ];
    }

    private function buildAddressLine(array $item): string
    {
        return collect([
            $item['line_1'] ?? null,
            $item['line_2'] ?? null,
            $item['line_3'] ?? null,
        ])
            ->filter()
            ->implode(', ');
    }
}