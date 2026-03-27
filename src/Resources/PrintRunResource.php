<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class PrintRunResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'issue_delivery_id' => $this->getAttribute('issue_delivery_id'),
            'workflow_run_id' => $this->getAttribute('workflow_run_id'),
            'status' => $this->getAttribute('status'),
            'is_regional' => $this->getAttribute('is_regional'),
            'territory_id' => $this->getAttribute('territory_id'),
            'driver_sync_enabled' => $this->getAttribute('driver_sync_enabled'),
            'driver_ref' => $this->getAttribute('driver_ref'),
            'driver_synced_at' => $this->getAttribute('driver_synced_at')?->format('Y-m-d H:i:s'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),
        ];
    }
}