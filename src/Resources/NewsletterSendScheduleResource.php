<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class NewsletterSendScheduleResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'newsletter_id' => $this->getAttribute('newsletter_id'),
            'site_id' => $this->getAttribute('site_id'),
            'creation_schedule_id' => $this->getAttribute('creation_schedule_id'),
            'frequency' => $this->getAttribute('frequency'),
            'day_of_week' => $this->getAttribute('day_of_week'),
            'day_of_month' => $this->getAttribute('day_of_month'),
            'time' => $this->getAttribute('time'),
            'status' => $this->getAttribute('status'),
            'next_run_at' => $this->getAttribute('next_run_at')?->format('Y-m-d H:i:s'),
            'last_run_at' => $this->getAttribute('last_run_at')?->format('Y-m-d H:i:s'),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getAttribute('updated_at')?->format('Y-m-d H:i:s'),

            // Computed state
            'is_active' => $this->resource->isActive(),
            'is_paused' => $this->resource->isPaused(),
            'is_cancelled' => $this->resource->isCancelled(),
        ];
    }
}