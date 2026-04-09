<?php

namespace App\Services\Members;

class FakeAddressLookupService implements AddressLookupServiceInterface
{
    public array $calls = [];
    public array $results = [];
    public bool $shouldThrow = false;

    public function lookup(string $postcode): array
    {
        $this->calls[] = $postcode;

        if ($this->shouldThrow) {
            throw new \Exception('Service failure');
        }

        return $this->results;
    }
}