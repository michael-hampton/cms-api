<?php

namespace App\Enums\Campaigns;

enum CampaignStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case DRAFT = 'draft';
    case ENDED = 'ended';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canBeResumed(): bool
    {
        return $this === self::PAUSED;
    }
}