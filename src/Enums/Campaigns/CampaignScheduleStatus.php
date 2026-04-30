<?php

namespace App\Enums\Campaigns;

enum CampaignScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Paused = 'paused';
    case Sent = 'sent';
}