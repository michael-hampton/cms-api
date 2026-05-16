<?php

namespace App\Enums\Subscriptions;

enum SubscriptionStrategyType: string
{
    case STANDARD     = 'standard';
    case TRIAL        = 'trial';
    case INTRO        = 'intro';
    case TRIAL_INTRO  = 'trial_intro'; // trial phase followed by intro phase before recurring
}