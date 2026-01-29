<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class MemberRewardResource extends JsonResource
{
    private ?array $statistics = null;

    public function __construct($resource, ?array $statistics = null)
    {
        parent::__construct($resource);
        $this->statistics = $statistics;
    }

    public static function makeWithStatistics($resource, array $statistics): self
    {
        return new self($resource, $statistics);
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->getAttribute('id'),
            'member_id' => $this->getAttribute('member_id'),
            'reward_definition_id' => $this->getAttribute('reward_definition_id'),
            'status' => $this->getAttribute('status'),
            'earned_at' => $this->getAttribute('earned_at')?->format('Y-m-d H:i:s'),
            'claimed_at' => $this->getAttribute('claimed_at')?->format('Y-m-d H:i:s'),
            'expires_at' => $this->getAttribute('expires_at')?->format('Y-m-d H:i:s'),
            'declined_at' => $this->getAttribute('declined_at')?->format('Y-m-d H:i:s'),
            'decline_reason' => $this->getAttribute('decline_reason'),
            'reward_data' => $this->getAttribute('reward_data'),
            'admin_notes' => $this->getAttribute('admin_notes'),

            // Relationships
            'member' => $this->whenLoaded('member'),
            'rewardDefinition' => $this->whenLoaded('rewardDefinition'),
            'voucherCode' => $this->whenLoaded('voucherCode'),
        ];

        if ($this->statistics !== null) {
            $data['statistics'] = $this->statistics;
        }

        return $data;
    }
}