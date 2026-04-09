<?php

namespace App\Services\Members;

interface AddressLookupServiceInterface
{
    public function lookup(string $postcode): array;
}