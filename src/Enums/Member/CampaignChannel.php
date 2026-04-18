<?php

namespace App\Enums\Member;

enum CampaignChannel: string
{
    case EMAIL = 'email';
    case NOTIFICATION = 'notification';
    case PUSH = 'push';
}