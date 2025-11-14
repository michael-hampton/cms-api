<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'name' => $this->resource['name'] ?? null,
            'company' => $this->resource['company'] ?? null,
            'address_line_1' => $this->resource['address_line_1'] ?? null,
            'address_line_2' => $this->resource['address_line_2'] ?? null,
            'city' => $this->resource['city'] ?? null,
            'state' => $this->resource['state'] ?? null,
            'postal_code' => $this->resource['postcode'] ?? null,
            'country' => $this->resource['country'] ?? null,
            'phone' => $this->resource['phone'] ?? null,
        ];
    }
}