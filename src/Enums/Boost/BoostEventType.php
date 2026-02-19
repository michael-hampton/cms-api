<?php

namespace App\Enums\Boost;

enum BoostEventType: string
{
    case Impression = 'impression';
    case Click = 'click';
    case Conversion = 'conversion';
}