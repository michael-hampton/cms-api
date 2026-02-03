<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class MemberResource extends JsonResource
{

    public function toArray(): array
    {
        return [
            'first_name' => $this->getAttribute('first_name'),
            'last_name' => $this->getAttribute('last_name'),
            'id' => $this->getAttribute('id'),
            'email' => $this->getAttribute('email'),
            'full_name' => $this->getAttribute('first_name') . ' ' . $this->getAttribute('last_name')
        ];
    }
}