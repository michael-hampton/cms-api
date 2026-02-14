<?php

namespace App\Enums;

enum ReviewModerationState: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}