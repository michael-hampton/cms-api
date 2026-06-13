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
                ->getAttribute('risk_type'),

            'source' => $this
                ->getAttribute('source'),

            'severity' => $this
                ->getAttribute('severity'),

            'status' => $this
                ->getAttribute('status'),

            'confidence' => $this->getAttribute('confidence'),
            'details' => $this->getAttribute('details'),
            'created_by_user_id' => $this->getAttribute('created_by_user_id'),

            'reviewed_at' => $this
                ->getAttribute('reviewed_at')
                ?->format('c'),

            'resolved_at' => $this
                ->getAttribute('resolved_at')
                ?->format('c'),

            'resolution_notes' => $this->getAttribute('resolution_notes'),
            'cms_image_id' => $this->getAttribute('cms_image_id'),
        ];
    }
}