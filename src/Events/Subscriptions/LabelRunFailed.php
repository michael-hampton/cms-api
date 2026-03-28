<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\LabelRun;

/**
 * Fired after a LabelRun transitions to Failed.
 *
 * Listeners may use this for alerting operators, triggering
 * retry logic, or updating batch-level observability counters.
 */
final class LabelRunFailed
{
    public function __construct(
        public readonly LabelRun $labelRun,
        public readonly string   $reason,
    )
    {
    }
}