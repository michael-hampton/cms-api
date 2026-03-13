<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class MerchantContactResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'merchant_id' => $this->getAttribute('merchant_id'),
            'name' => $this->getAttribute('name'),
            'email' => $this->getAttribute('email'),
            'phone' => $this->getAttribute('phone'),
            'role' => $this->getAttribute('role'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),

            'merchant' => $this->whenLoaded('merchant', fn() => [
                'id' => $this->resource->merchant->id,
                'name' => $this->resource->merchant->name,
            ]),
        ];
    }
}