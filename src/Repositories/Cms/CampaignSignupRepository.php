<?php

namespace App\Repositories\Cms;

use App\Models\CampaignSignup;
use App\Models\Model;

class CampaignSignupRepository
{
    public function create(array $data): Model
    {
        return CampaignSignup::create([
            'campaign_id' => $data['campaign_id'],
            'site_id' => $data['site_id'],
            'user_id' => $data['user_id'] ?? null,
            'email' => $data['email'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'referrer' => $data['referrer'] ?? null,
        ]);
    }
}
