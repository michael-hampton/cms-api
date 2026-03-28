<?php

declare(strict_types=1);

namespace App\Enums\Products;

enum ProductFulfilmentRunStatus: string
{
    case PENDING = 'pending';
    case FULFILLING = 'fulfilling';
    case BATCHING = 'batching';
    case BATCHED = 'batched';
    case COMPLETE = 'complete';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}