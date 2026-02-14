<?php

namespace App\Enums;

enum ConsentCategory: string
{
    case ESSENTIAL = 'essential';
    case MARKETING = 'marketing';
    case ANALYTICS = 'analytics';
    case PREFERENCES = 'preferences';
}