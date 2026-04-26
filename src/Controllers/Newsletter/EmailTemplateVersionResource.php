<?php

namespace App\Controllers\Newsletter;

use App\Framework\Resource\JsonResource;

class EmailTemplateVersionResource extends JsonResource
{

    public function toArray(): array
    {
        return [
            'version_number' => $this->getAttribute('version_number'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'created_by_name' => $this->getAttribute('creator.name'),
            'snapshot' => $this->getAttribute('layout_definition_json')
        ];
    }
}