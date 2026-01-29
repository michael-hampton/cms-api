<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class RewardStatisticsResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'definition_id' => $this->getAttribute('definition_id'),
            'definition_name' => $this->getAttribute('definition_name'),
            'total_rewards' => $this->getAttribute('total_rewards'),
            'claimed' => $this->getAttribute('claimed'),
            'pending' => $this->getAttribute('pending'),
            'expired' => $this->getAttribute('expired'),
            'declined' => $this->getAttribute('declined'),
            'claim_rate' => $this->getAttribute('claim_rate'),
            'total_clicks' => $this->getAttribute('total_clicks'),
            'unique_clickers' => $this->getAttribute('unique_clickers'),
            'click_through_rate' => $this->getAttribute('click_through_rate'),
            'clicks_by_action' => $this->getAttribute('clicks_by_action'),
            'recent_clicks' => $this->getAttribute('recent_clicks'),
        ];
    }
}