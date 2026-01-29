<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class OfferStatisticsResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'total_offers' => $this->getAttribute('total_offers'),
            'active_offers' => $this->getAttribute('active_offers'),
            'running_offers' => $this->getAttribute('running_offers'),
            'published_offers' => $this->getAttribute('published_offers'),
            'pending_offers' => $this->getAttribute('pending_offers'),
            'rejected_offers' => $this->getAttribute('rejected_offers'),
            'total_clicks' => $this->getAttribute('total_clicks'),
            'unique_clickers' => $this->getAttribute('unique_clickers'),
            'click_through_rate' => $this->getAttribute('click_through_rate'),
            'top_offers' => $this->getAttribute('top_offers'),
        ];
    }
}