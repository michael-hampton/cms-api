<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;
use App\Models\ContentRiskMarker;

class RiskMarkerResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),

            'risk_type' => $this
                ->getAttribute('risk_type')
                ?->value,

            'source' => $this
                ->getAttribute('source')
                ?->value,

            'severity' => $this
                ->getAttribute('severity')
                ?->value,

            'status' => $this
                ->getAttribute('status')
                ?->value,

            'confidence' => $this->getAttribute('confidence'),
            'details' => $this->getAttribute('details'),
            'created_by_user_id' => $this->marker->getAttribute('created_by_user_id'),

            'reviewed_at' => $this
                ->getAttribute('reviewed_at')
                ?->toIso8601String(),

            'resolved_at' => $this
                ->getAttribute('resolved_at')
                ?->toIso8601String(),

            'resolution_notes' => $this->getAttribute('resolution_notes'),
            'cms_image_id' => $this->getAttribute('cms_image_id'),
        ];
    }
}