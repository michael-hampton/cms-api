<?php

namespace App\DTO\Campaigns;

class CampaignResolutionResult
{
    public function __construct(
        public bool    $success,
        public ?int    $newsletterId = null,
        public ?int    $campaignId = null,
        public ?array  $campaign = null,
        public ?string $error = null,
    )
    {
    }
}