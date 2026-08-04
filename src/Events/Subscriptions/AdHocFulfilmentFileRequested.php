<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\AdHocFulfilmentRequest;

final class AdHocFulfilmentFileRequested
{
    public function __construct(
        public readonly AdHocFulfilmentRequest $request,
    ) {
    }
}