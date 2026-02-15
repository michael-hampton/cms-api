<?php

namespace App\Enums\Refunds;

enum RefundStatus: string
{
    case PENDING = 'pending';
    case PROCESSED = 'processed';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
}