<?php

namespace App\Enums\Boost;

enum BoostStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Paused = 'paused';
}