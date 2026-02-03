<?php

namespace App\Enums;

enum RewardClickAction: string
{
    case VIEW = 'view';
    case CLAIM = 'claim';
    case CLICK = 'click';
}