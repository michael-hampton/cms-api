<?php

namespace App\Enums\Rewards;

enum RewardClickAction: string
{
    case VIEW = 'view';
    case CLAIM = 'claim';
    case CLICK = 'click';
}